const express = require('express');
const http = require('http');
const cors = require('cors');
const qrcode = require('qrcode');
const path = require('path');
const fs = require('fs');
const pino = require('pino');
const {
    default: makeWASocket,
    DisconnectReason,
    useMultiFileAuthState,
    fetchLatestBaileysVersion
} = require('@whiskeysockets/baileys');

const app = express();
const PORT = process.env.WHATSAPP_SESSION_PORT || 3001;
const SECRET_KEY = process.env.WHATSAPP_SESSION_SECRET || 'wa_session_secret_key_2026';

app.use(cors());
app.use(express.json());

// In-memory sessions store
// Map<string, { sock, status, qr, qrRaw, phone, name, lastActive, authDir }>
const sessions = new Map();

const SESSIONS_DIR = path.join(__dirname, 'auth_sessions');
if (!fs.existsSync(SESSIONS_DIR)) {
    fs.mkdirSync(SESSIONS_DIR, { recursive: true });
}

// Simple authentication middleware
const authMiddleware = (req, res, next) => {
    const clientSecret = req.headers['x-session-secret'] || req.query.secret;
    if (SECRET_KEY && clientSecret !== SECRET_KEY) {
        return res.status(401).json({ status: false, message: 'Unauthorized session request' });
    }
    next();
};

app.use(authMiddleware);

// Health check endpoint
app.get('/health', (req, res) => {
    res.json({
        status: 'ok',
        uptime: process.uptime(),
        active_sessions: sessions.size,
        timestamp: new Date().toISOString()
    });
});

/**
 * Initialize or start a WhatsApp session
 */
async function startSession(sessionId) {
    if (sessions.has(sessionId)) {
        const existing = sessions.get(sessionId);
        if (existing.status === 'connected' && existing.sock) {
            return existing;
        }
    }

    const sessionAuthDir = path.join(SESSIONS_DIR, sessionId);
    if (!fs.existsSync(sessionAuthDir)) {
        fs.mkdirSync(sessionAuthDir, { recursive: true });
    }

    const { state, saveCreds } = await useMultiFileAuthState(sessionAuthDir);
    const { version } = await fetchLatestBaileysVersion();

    const sessionData = {
        sock: null,
        status: 'initializing',
        qr: null,
        qrRaw: null,
        phone: null,
        name: null,
        lastActive: new Date(),
        authDir: sessionAuthDir
    };
    sessions.set(sessionId, sessionData);

    const sock = makeWASocket({
        version,
        auth: state,
        printQRInTerminal: false,
        logger: pino({ level: 'silent' }),
        browser: ['Chrome (Windows)', 'Desktop', '120.0.0']
    });

    sessionData.sock = sock;

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            sessionData.status = 'qr_ready';
            sessionData.qrRaw = qr;
            try {
                sessionData.qr = await qrcode.toDataURL(qr, { margin: 2, scale: 6 });
            } catch (err) {
                console.error(`QR generation error for ${sessionId}:`, err);
            }
        }

        if (connection === 'open') {
            sessionData.status = 'connected';
            sessionData.qr = null;
            sessionData.qrRaw = null;
            const userJid = sock.user?.id || '';
            sessionData.phone = userJid.split(':')[0] || userJid.split('@')[0];
            sessionData.name = sock.user?.name || 'WhatsApp User';
            sessionData.lastActive = new Date();
            console.log(`[WhatsApp Session] ${sessionId} connected successfully (${sessionData.phone})`);
        }

        if (connection === 'close') {
            const statusCode = lastDisconnect?.error?.output?.statusCode;
            const shouldReconnect = statusCode !== DisconnectReason.loggedOut;

            console.log(`[WhatsApp Session] ${sessionId} closed. Reconnect: ${shouldReconnect}, Code: ${statusCode}`);

            if (statusCode === DisconnectReason.loggedOut) {
                sessionData.status = 'disconnected';
                sessionData.qr = null;
                sessionData.sock = null;
                try {
                    fs.rmSync(sessionAuthDir, { recursive: true, force: true });
                } catch (e) {}
            } else if (shouldReconnect) {
                sessionData.status = 'reconnecting';
                setTimeout(() => startSession(sessionId), 3000);
            } else {
                sessionData.status = 'disconnected';
                sessionData.sock = null;
            }
        }
    });

    return sessionData;
}

// 1. Initialize or connect session
app.post('/sessions/init', async (req, res) => {
    try {
        const { session_id } = req.body;
        if (!session_id) {
            return res.status(400).json({ status: false, message: 'session_id is required' });
        }

        const session = await startSession(session_id);

        res.json({
            status: session.status,
            session_id,
            qr: session.qr,
            qr_raw: session.qrRaw,
            phone: session.phone,
            name: session.name
        });
    } catch (error) {
        console.error('Error in /sessions/init:', error);
        res.status(500).json({ status: 'error', message: error.message });
    }
});

// 2. Get session status & QR
app.get('/sessions/:sessionId/status', async (req, res) => {
    const { sessionId } = req.params;
    let session = sessions.get(sessionId);

    // If session not in memory but directory exists on disk, restore it
    if (!session) {
        const sessionAuthDir = path.join(SESSIONS_DIR, sessionId);
        if (fs.existsSync(sessionAuthDir) && fs.existsSync(path.join(sessionAuthDir, 'creds.json'))) {
            try {
                session = await startSession(sessionId);
            } catch (e) {
                return res.json({ status: 'disconnected', is_connected: false });
            }
        } else {
            return res.json({ status: 'disconnected', is_connected: false });
        }
    }

    res.json({
        status: session.status,
        session_id: sessionId,
        phone: session.phone,
        name: session.name,
        qr: session.qr,
        qr_raw: session.qrRaw,
        is_connected: session.status === 'connected'
    });
});

// 3. Disconnect session
app.delete('/sessions/:sessionId', async (req, res) => {
    const { sessionId } = req.params;
    const session = sessions.get(sessionId);

    if (session && session.sock) {
        try {
            await session.sock.logout();
        } catch (e) {}
    }

    const sessionAuthDir = path.join(SESSIONS_DIR, sessionId);
    try {
        fs.rmSync(sessionAuthDir, { recursive: true, force: true });
    } catch (e) {}

    sessions.delete(sessionId);

    res.json({ status: true, message: 'Session disconnected and deleted' });
});

// 4. Fetch all groups for a connected session
app.get('/sessions/:sessionId/groups', async (req, res) => {
    const { sessionId } = req.params;
    const session = sessions.get(sessionId);

    if (!session || session.status !== 'connected' || !session.sock) {
        return res.status(400).json({
            status: false,
            message: 'Session is not connected to WhatsApp. Please scan QR code first.'
        });
    }

    try {
        const groupsData = await session.sock.groupFetchAllParticipating();
        const groups = Object.values(groupsData).map(g => ({
            jid: g.id,
            name: g.subject || 'Unnamed Group',
            participants_count: Array.isArray(g.participants) ? g.participants.length : 0,
            creation: g.creation ? new Date(g.creation * 1000).toISOString() : null,
            owner: g.owner || null,
            desc: g.desc ? g.desc.toString() : ''
        }));

        res.json({
            status: true,
            total: groups.length,
            groups
        });
    } catch (error) {
        console.error(`Error fetching groups for ${sessionId}:`, error);
        res.status(500).json({ status: false, message: error.message });
    }
});

// 5. Fetch participants of a specific group
app.get('/sessions/:sessionId/groups/:groupJid/participants', async (req, res) => {
    const { sessionId, groupJid } = req.params;
    const session = sessions.get(sessionId);

    if (!session || session.status !== 'connected' || !session.sock) {
        return res.status(400).json({
            status: false,
            message: 'Session is not connected to WhatsApp.'
        });
    }

    try {
        const metadata = await session.sock.groupMetadata(groupJid);
        const participants = (metadata.participants || []).map(p => {
            const cleanJid = p.id || '';
            const phone = cleanJid.split('@')[0].split(':')[0];
            return {
                jid: cleanJid,
                phone: phone,
                name: null, // personal WhatsApp privacy hides names unless in address book
                isAdmin: p.admin === 'admin' || p.admin === 'superadmin'
            };
        });

        res.json({
            status: true,
            group_name: metadata.subject,
            total: participants.length,
            participants
        });
    } catch (error) {
        console.error(`Error fetching participants for ${sessionId} group ${groupJid}:`, error);
        res.status(500).json({ status: false, message: error.message });
    }
});

// Restore previously active sessions on boot
async function restoreSavedSessions() {
    try {
        if (!fs.existsSync(SESSIONS_DIR)) return;
        const subdirs = fs.readdirSync(SESSIONS_DIR, { withFileTypes: true });
        for (const dirent of subdirs) {
            if (dirent.isDirectory()) {
                const credsFile = path.join(SESSIONS_DIR, dirent.name, 'creds.json');
                if (fs.existsSync(credsFile)) {
                    console.log(`[WhatsApp Session] Restoring saved session: ${dirent.name}`);
                    await startSession(dirent.name).catch(err => {
                        console.error(`Failed to restore ${dirent.name}:`, err.message);
                    });
                }
            }
        }
    } catch (err) {
        console.error('Error restoring sessions:', err);
    }
}

// 1. TCP Port Listener (Windows XAMPP & Linux local)
try {
    app.listen(PORT, '127.0.0.1', () => {
        console.log(`WhatsApp Multi-Device Session Bridge running on port ${PORT}`);
    });
} catch (e) {
    console.error('TCP Port Listen Error:', e.message);
}

// 2. UNIX Domain Socket Listener (Essential for Hostinger / CageFS shared hosting)
if (process.platform !== 'win32') {
    const SOCKET_PATH = process.env.WHATSAPP_SESSION_SOCKET || path.join(__dirname, 'whatsapp.sock');
    if (fs.existsSync(SOCKET_PATH)) {
        try { fs.unlinkSync(SOCKET_PATH); } catch (e) {}
    }
    const unixServer = http.createServer(app);
    unixServer.listen(SOCKET_PATH, () => {
        try { fs.chmodSync(SOCKET_PATH, 0o777); } catch (e) {}
        console.log(`WhatsApp Multi-Device Session Bridge running on socket ${SOCKET_PATH}`);
    });
}

restoreSavedSessions();
