// server.js - Serwer Node.js z Socket.IO dla komunikacji w czasie rzeczywistym

const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const cors = require('cors');
const mysql = require('mysql2/promise');

// Konfiguracja
const PORT = process.env.PORT || 3000;
const DB_CONFIG = {
    host: 'localhost',
    user: 'root',
    password: '',
    database: 'chat_app'
};

// Inicjalizacja aplikacji
const app = express();
const server = http.createServer(app);
const io = socketIo(server, {
    cors: {
        origin: "*",
        methods: ["GET", "POST"]
    }
});

// Middleware
app.use(cors());
app.use(express.json());

// Mapa aktywnych użytkowników (user_id -> socket_id)
const activeUsers = new Map();
const userSockets = new Map(); // socket_id -> user_data

// Połączenie z bazą danych
let db;
async function connectDB() {
    try {
        db = await mysql.createConnection(DB_CONFIG);
        console.log('Połączono z bazą danych MySQL');
    } catch (error) {
        console.error('Błąd połączenia z bazą danych:', error);
        process.exit(1);
    }
}

// Funkcja do aktualizacji statusu online użytkownika
async function updateUserOnlineStatus(userId, isOnline) {
    try {
        await db.execute(
            'UPDATE users SET is_online = ?, last_seen = NOW() WHERE id = ?',
            [isOnline, userId]
        );
    } catch (error) {
        console.error('Błąd aktualizacji statusu:', error);
    }
}

// Funkcja do powiadamiania znajomych o zmianie statusu
async function notifyFriendsStatusChange(userId, status) {
    try {
        // Pobierz znajomych użytkownika
        const [friends] = await db.execute(`
            SELECT f.friend_id 
            FROM friends f 
            WHERE f.user_id = ? AND f.status = 'accepted'
        `, [userId]);

        // Powiadom aktywnych znajomych
        friends.forEach(friend => {
            const friendSocketId = activeUsers.get(friend.friend_id);
            if (friendSocketId) {
                io.to(friendSocketId).emit('user_status_changed', {
                    user_id: userId,
                    status: status
                });
            }
        });
    } catch (error) {
        console.error('Błąd powiadamiania znajomych:', error);
    }
}

// Funkcja do zapisywania wiadomości w bazie danych
async function saveMessage(senderId, receiverId, message) {
    try {
        const [result] = await db.execute(
            'INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)',
            [senderId, receiverId, message]
        );
        
        // Pobierz szczegóły wiadomości
        const [messageData] = await db.execute(`
            SELECT m.*, 
                   s.username as sender_username, 
                   s.full_name as sender_name,
                   r.username as receiver_username, 
                   r.full_name as receiver_name
            FROM messages m
            JOIN users s ON m.sender_id = s.id
            JOIN users r ON m.receiver_id = r.id
            WHERE m.id = ?
        `, [result.insertId]);
        
        return messageData[0];
    } catch (error) {
        console.error('Błąd zapisywania wiadomości:', error);
        return null;
    }
}

// Funkcja do sprawdzenia czy użytkownicy są znajomymi
async function areFriends(userId1, userId2) {
    try {
        const [result] = await db.execute(`
            SELECT id FROM friends 
            WHERE user_id = ? AND friend_id = ? AND status = 'accepted'
        `, [userId1, userId2]);
        
        return result.length > 0;
    } catch (error) {
        console.error('Błąd sprawdzania znajomości:', error);
        return false;
    }
}

// Socket.IO event handlers
io.on('connection', (socket) => {
    console.log('Nowy użytkownik połączony:', socket.id);

    // Użytkownik się loguje/łączy
    socket.on('user_online', async (userId) => {
        try {
            console.log(`Użytkownik ${userId} jest online`);
            
            // Zapisz mapowanie użytkownika
            activeUsers.set(userId, socket.id);
            userSockets.set(socket.id, { user_id: userId });
            
            // Aktualizuj status w bazie danych
            await updateUserOnlineStatus(userId, true);
            
            // Powiadom znajomych
            await notifyFriendsStatusChange(userId, 'online');
            
            // Dołącz do pokoju użytkownika
            socket.join(`user_${userId}`);
            
        } catch (error) {
            console.error('Błąd user_online:', error);
        }
    });

    // Użytkownik jest nieaktywny (ale wciąż połączony)
    socket.on('user_away', async (userId) => {
        try {
            console.log(`Użytkownik ${userId} jest nieaktywny`);
            await notifyFriendsStatusChange(userId, 'away');
        } catch (error) {
            console.error('Błąd user_away:', error);
        }
    });

    // Użytkownik się wylogowuje
    socket.on('user_offline', async (userId) => {
        try {
            console.log(`Użytkownik ${userId} jest offline`);
            
            // Usuń z aktywnych użytkowników
            activeUsers.delete(userId);
            userSockets.delete(socket.id);
            
            // Aktualizuj status w bazie danych
            await updateUserOnlineStatus(userId, false);
            
            // Powiadom znajomych
            await notifyFriendsStatusChange(userId, 'offline');
            
        } catch (error) {
            console.error('Błąd user_offline:', error);
        }
    });

    // Wysyłanie wiadomości
    socket.on('send_message', async (data) => {
        try {
            const { sender_id, receiver_id, message } = data;
            
            // Sprawdź czy użytkownicy są znajomymi
            const friendsCheck = await areFriends(sender_id, receiver_id);
            if (!friendsCheck) {
                socket.emit('error', { message: 'Możesz wysyłać wiadomości tylko do znajomych' });
                return;
            }
            
            // Zapisz wiadomość w bazie danych
            const savedMessage = await saveMessage(sender_id, receiver_id, message);
            
            if (savedMessage) {
                // Wyślij wiadomość do odbiorcy (jeśli jest online)
                const receiverSocketId = activeUsers.get(receiver_id);
                if (receiverSocketId) {
                    io.to(receiverSocketId).emit('new_message', savedMessage);
                }
                
                // Potwierdź nadawcy
                socket.emit('message_sent', savedMessage);
                
                console.log(`Wiadomość od ${sender_id} do ${receiver_id}: ${message}`);
            }
            
        } catch (error) {
            console.error('Błąd send_message:', error);
            socket.emit('error', { message: 'Wystąpił błąd podczas wysyłania wiadomości' });
        }
    });

    // Powiadamianie o pisaniu
    socket.on('typing', async (data) => {
        try {
            const { user_id, username, receiver_id } = data;
            
            // Wyślij powiadomienie do odbiorcy
            const receiverSocketId = activeUsers.get(receiver_id);
            if (receiverSocketId) {
                io.to(receiverSocketId).emit('user_typing', {
                    user_id: user_id,
                    username: username
                });
            }
            
        } catch (error) {
            console.error('Błąd typing:', error);
        }
    });

    // Zakończenie pisania
    socket.on('stop_typing', async (data) => {
        try {
            const { user_id, receiver_id } = data;
            
            // Wyślij powiadomienie do odbiorcy
            const receiverSocketId = activeUsers.get(receiver_id);
            if (receiverSocketId) {
                io.to(receiverSocketId).emit('user_stopped_typing', {
                    user_id: user_id
                });
            }
            
        } catch (error) {
            console.error('Błąd stop_typing:', error);
        }
    });

    // Dołączanie do pokoju rozmowy
    socket.on('join_conversation', (data) => {
        const { user_id, friend_id } = data;
        const roomName = `conversation_${Math.min(user_id, friend_id)}_${Math.max(user_id, friend_id)}`;
        socket.join(roomName);
        console.log(`Użytkownik ${user_id} dołączył do rozmowy: ${roomName}`);
    });

    // Opuszczanie pokoju rozmowy
    socket.on('leave_conversation', (data) => {
        const { user_id, friend_id } = data;
        const roomName = `conversation_${Math.min(user_id, friend_id)}_${Math.max(user_id, friend_id)}`;
        socket.leave(roomName);
        console.log(`Użytkownik ${user_id} opuścił rozmowę: ${roomName}`);
    });

    // Powiadamianie o przeczytaniu wiadomości
    socket.on('messages_read', async (data) => {
        try {
            const { reader_id, sender_id } = data;
            
            // Aktualizuj wiadomości jako przeczytane w bazie danych
            await db.execute(
                'UPDATE messages SET is_read = TRUE WHERE receiver_id = ? AND sender_id = ? AND is_read = FALSE',
                [reader_id, sender_id]
            );
            
            // Powiadom nadawcę o przeczytaniu
            const senderSocketId = activeUsers.get(sender_id);
            if (senderSocketId) {
                io.to(senderSocketId).emit('messages_read', {
                    reader_id: reader_id,
                    timestamp: new Date()
                });
            }
            
        } catch (error) {
            console.error('Błąd messages_read:', error);
        }
    });

    // Rozłączenie użytkownika
    socket.on('disconnect', async () => {
        try {
            console.log('Użytkownik rozłączony:', socket.id);
            
            const userData = userSockets.get(socket.id);
            if (userData) {
                const userId = userData.user_id;
                
                // Usuń z aktywnych użytkowników
                activeUsers.delete(userId);
                userSockets.delete(socket.id);
                
                // Aktualizuj status w bazie danych
                await updateUserOnlineStatus(userId, false);
                
                // Powiadom znajomych
                await notifyFriendsStatusChange(userId, 'offline');
            }
            
        } catch (error) {
            console.error('Błąd disconnect:', error);
        }
    });

    // Obsługa błędów
    socket.on('error', (error) => {
        console.error('Socket error:', error);
    });
});

// API endpoints dla integracji z PHP
app.get('/api/online-users', (req, res) => {
    const onlineUserIds = Array.from(activeUsers.keys());
    res.json({ online_users: onlineUserIds });
});

app.get('/api/user-status/:userId', (req, res) => {
    const userId = parseInt(req.params.userId);
    const isOnline = activeUsers.has(userId);
    res.json({ user_id: userId, is_online: isOnline });
});

// Endpoint do wysyłania powiadomień push (dla przyszłego rozwoju)
app.post('/api/send-notification', async (req, res) => {
    try {
        const { user_id, title, message, data } = req.body;
        
        const socketId = activeUsers.get(user_id);
        if (socketId) {
            io.to(socketId).emit('notification', {
                title: title,
                message: message,
                data: data,
                timestamp: new Date()
            });
            
            res.json({ success: true, message: 'Powiadomienie wysłane' });
        } else {
            res.json({ success: false, message: 'Użytkownik nie jest online' });
        }
        
    } catch (error) {
        console.error('Błąd send-notification:', error);
        res.status(500).json({ success: false, message: 'Błąd serwera' });
    }
});

// Funkcja czyszcząca nieaktywne połączenia (uruchamiana co 5 minut)
setInterval(async () => {
    try {
        console.log('Czyszczenie nieaktywnych połączeń...');
        
        // Sprawdź wszystkie aktywne sockety
        const currentSockets = new Set();
        io.sockets.sockets.forEach((socket) => {
            currentSockets.add(socket.id);
        });
        
        // Usuń nieaktywne mapowania
        for (const [socketId, userData] of userSockets.entries()) {
            if (!currentSockets.has(socketId)) {
                console.log(`Usuwanie nieaktywnego połączenia: ${socketId}`);
                activeUsers.delete(userData.user_id);
                userSockets.delete(socketId);
                
                // Aktualizuj status w bazie danych
                await updateUserOnlineStatus(userData.user_id, false);
                await notifyFriendsStatusChange(userData.user_id, 'offline');
            }
        }
        
        console.log(`Aktywnych użytkowników: ${activeUsers.size}`);
        
    } catch (error) {
        console.error('Błąd podczas czyszczenia:', error);
    }
}, 5 * 60 * 1000); // 5 minut

// Graceful shutdown
process.on('SIGINT', async () => {
    console.log('Zamykanie serwera...');
    
    // Oznacz wszystkich użytkowników jako offline
    for (const userId of activeUsers.keys()) {
        await updateUserOnlineStatus(userId, false);
    }
    
    if (db) {
        await db.end();
    }
    
    server.close(() => {
        console.log('Serwer zamknięty');
        process.exit(0);
    });
});

// Uruchomienie serwera
async function startServer() {
    await connectDB();
    
    server.listen(PORT, () => {
        console.log(`Serwer Socket.IO uruchomiony na porcie ${PORT}`);
        console.log(`Aktywnych użytkowników: ${activeUsers.size}`);
    });
}

startServer().catch(error => {
    console.error('Błąd uruchomienia serwera:', error);
    process.exit(1);
});

module.exports = { app, server, io };