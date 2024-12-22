<?php
$db_host = 'localhost';
$db_name = 'ai_chat';
$db_user = 'root';
$db_pass = '';

try {
    $db = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $db->exec("CREATE TABLE IF NOT EXISTS conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        input TEXT NOT NULL,
        bot_id VARCHAR(50) NOT NULL,
        response TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS bots (
        id VARCHAR(50) PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        learning_type VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $bots = [
        ['pattern_matcher', 'Pattern Bot', 'Uses pattern matching and templates', 'pattern'],
        ['markov_chain', 'Markov Bot', 'Uses Markov chains for response generation', 'markov'],
        ['similarity_learner', 'Similarity Bot', 'Uses word similarity and context', 'similarity'],
        ['neural_net', 'Neural Bot', 'Uses simple neural network', 'neural'],
        ['simple_ai', 'Simple AI', 'Uses the original SimpleAI implementation', 'simple']
    ];

    $stmt = $db->prepare("INSERT IGNORE INTO bots (id, name, description, learning_type) VALUES (?, ?, ?, ?)");
    foreach ($bots as $bot) {
        $stmt->execute($bot);
    }
} catch(PDOException $e) {
    die("Database setup failed: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    try {
        switch($_POST['action']) {
            case 'save_conversation':
                $input = $_POST['input'] ?? '';
                $response = $_POST['response'] ?? '';
                $botId = $_POST['bot_id'] ?? '';
                
                $stmt = $db->prepare("INSERT INTO conversations (input, response, bot_id) VALUES (?, ?, ?)");
                $result = $stmt->execute([$input, $response, $botId]);
                echo json_encode(['success' => $result]);
                exit;
            case 'get_training_data':
                $stmt = $db->query("SELECT input, response, bot_id FROM conversations ORDER BY created_at DESC LIMIT 1000");
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['data' => $data]);
                exit;
            case 'get_bots':
                $stmt = $db->query("SELECT * FROM bots ORDER BY name");
                $bots = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['bots' => $bots]);
                exit;
        }
    } catch(Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Bot AI Chat</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tensorflow/4.10.0/tf.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; background-color: #f5f5f5; }
        .chat-container { display: grid; grid-template-columns: 250px 1fr; gap: 20px; background-color: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .bot-list { border-right: 1px solid #ddd; padding-right: 20px; }
        .bot-item { padding: 10px; margin: 5px 0; border-radius: 5px; }
        .bot-checkbox { margin-right: 10px; }
        .chat-messages { height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin-bottom: 20px; border-radius: 5px; }
        .message { margin: 10px 0; padding: 10px; border-radius: 5px; position: relative; }
        .bot-name { font-size: 0.8em; color: #666; margin-bottom: 5px; }
        .user-message { background-color: #e3f2fd; margin-left: 20%; }
        .ai-message { background-color: #f5f5f5; margin-right: 20%; }
        .input-container { display: flex; gap: 10px; }
        input[type="text"] { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        button { padding: 10px 20px; background-color: #2196f3; color: white; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background-color: #1976d2; }
        .status { margin-top: 10px; padding: 10px; border-radius: 5px; }
        .bot-controls { padding: 10px; border-bottom: 1px solid #ddd; margin-bottom: 10px; }
        .select-all { margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="bot-list" id="botList">
            <h2>AI Bots</h2>
            <div class="bot-controls">
                <button onclick="toggleAllBots(true)">Select All</button>
                <button onclick="toggleAllBots(false)">Deselect All</button>
            </div>
        </div>
        <div class="chat-content">
            <h1>Multi-Bot AI Chat</h1>
            <div class="chat-messages" id="chatMessages"></div>
            <div class="input-container">
                <input type="text" id="userInput" placeholder="Type your message...">
                <button onclick="sendMessage()">Send</button>
            </div>
            <div id="status" class="status"></div>
        </div>
    </div>
    <script>
class SimpleAI {
    constructor() {
        this.model = null;
        this.vocabulary = new Map();
        this.responses = [];
        this.id = 'simple_ai';
        this.name = 'Simple AI';
    }

    async initialize() {
        if (!this.model) {
            this.model = tf.sequential({
                layers: [
                    tf.layers.dense({ units: 64, activation: 'relu', inputShape: [100] }),
                    tf.layers.dense({ units: 32, activation: 'relu' }),
                    tf.layers.dense({ units: 64, activation: 'relu' }),
                    tf.layers.dense({ units: 100, activation: 'softmax' })
                ]
            });

            this.model.compile({
                optimizer: 'adam',
                loss: 'categoricalCrossentropy',
                metrics: ['accuracy']
            });

            await this.loadTrainingData();
        }
    }

    async loadTrainingData() {
        try {
            const formData = new FormData();
            formData.append('action', 'get_training_data');
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            this.responses = data.data || [];
            
            this.responses.forEach(item => {
                const words = item.response.toLowerCase().split(/\s+/);
                words.forEach(word => {
                    if (!this.vocabulary.has(word)) {
                        this.vocabulary.set(word, this.vocabulary.size);
                    }
                });
            });
        } catch (error) {
            console.error('Error loading training data:', error);
        }
    }

    async generateResponse(input) {
        const words = input.toLowerCase().split(/\s+/);
        let response = "I'm learning to respond better. ";
        
        if (this.responses.length > 0) {
            let bestMatch = this.responses[0];
            let bestScore = 0;
            
            this.responses.forEach(item => {
                const score = this.calculateSimilarity(words, item.input.toLowerCase().split(/\s+/));
                if (score > bestScore) {
                    bestScore = score;
                    bestMatch = item;
                }
            });
            
            if (bestScore > 0.3) {
                response = bestMatch.response;
            }
        }
        
        return response;
    }

    calculateSimilarity(words1, words2) {
        const set1 = new Set(words1);
        const set2 = new Set(words2);
        const intersection = new Set([...set1].filter(x => set2.has(x)));
        const union = new Set([...set1, ...set2]);
        return intersection.size / union.size;
    }

    async saveResponse(input, response) {
        const formData = new FormData();
        formData.append('action', 'save_conversation');
        formData.append('input', input);
        formData.append('response', response);
        formData.append('bot_id', this.id);
        
        try {
            await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            await this.loadTrainingData();
        } catch (error) {
            console.error('Error saving response:', error);
        }
    }
}

class BaseBot {
    constructor(id, name) {
        this.id = id;
        this.name = name;
        this.trainingData = [];
    }

    async initialize() {
        await this.loadTrainingData();
    }

    async loadTrainingData() {
        try {
            const formData = new FormData();
            formData.append('action', 'get_training_data');
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            this.trainingData = data.data || [];
        } catch (error) {
            console.error('Error loading training data:', error);
        }
    }

    async saveResponse(input, response) {
        const formData = new FormData();
        formData.append('action', 'save_conversation');
        formData.append('input', input);
        formData.append('response', response);
        formData.append('bot_id', this.id);
        try {
            await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            await this.loadTrainingData();
        } catch (error) {
            console.error('Error saving response:', error);
        }
    }
}

class PatternBot extends BaseBot {
    constructor() {
        super('pattern_matcher', 'Pattern Bot');
        this.patterns = [
            { pattern: /hello|hi|hey/i, responses: ['Hello!', 'Hi there!', 'Hey!'] },
            { pattern: /how are you/i, responses: ['I\'m doing well!', 'Great, thanks for asking!'] },
            { pattern: /bye|goodbye/i, responses: ['Goodbye!', 'See you later!', 'Bye!'] }
        ];
    }

    async generateResponse(input) {
        for (const pattern of this.patterns) {
            if (pattern.pattern.test(input)) {
                return pattern.responses[Math.floor(Math.random() * pattern.responses.length)];
            }
        }
        return "I'm not sure how to respond to that yet.";
    }
}

class MarkovBot extends BaseBot {
    constructor() {
        super('markov_chain', 'Markov Bot');
        this.chain = new Map();
    }

    async initialize() {
        await super.initialize();
        this.buildChain();
    }

    buildChain() {
        this.chain.clear();
        this.trainingData.forEach(item => {
            const words = item.response.split(' ');
            for (let i = 0; i < words.length - 1; i++) {
                const current = words[i];
                const next = words[i + 1];
                if (!this.chain.has(current)) {
                    this.chain.set(current, []);
                }
                this.chain.get(current).push(next);
            }
        });
    }

    async generateResponse(input) {
        if (this.chain.size === 0) {
            return "Still learning...";
        }

        let current = Array.from(this.chain.keys())[Math.floor(Math.random() * this.chain.size)];
        let response = [current];
        
        for (let i = 0; i < 15; i++) {
            const nextWords = this.chain.get(current);
            if (!nextWords || nextWords.length === 0) break;
            current = nextWords[Math.floor(Math.random() * nextWords.length)];
            response.push(current);
        }

        return response.join(' ');
    }
}

class SimilarityBot extends BaseBot {
    constructor() {
        super('similarity_learner', 'Similarity Bot');
    }

    calculateSimilarity(words1, words2) {
        const set1 = new Set(words1);
        const set2 = new Set(words2);
        const intersection = new Set([...set1].filter(x => set2.has(x)));
        const union = new Set([...set1, ...set2]);
        return intersection.size / union.size;
    }

    async generateResponse(input) {
        const words = input.toLowerCase().split(/\s+/);
        
        if (this.trainingData.length === 0) {
            return "Learning from conversations...";
        }

        let bestMatch = this.trainingData[0];
        let bestScore = 0;

        this.trainingData.forEach(item => {
            const score = this.calculateSimilarity(
                words,
                item.input.toLowerCase().split(/\s+/)
            );
            if (score > bestScore) {
                bestScore = score;
                bestMatch = item;
            }
        });

        return bestScore > 0.2 ? bestMatch.response : "I'm still learning about this topic.";
    }
}

class NeuralBot extends BaseBot {
    constructor() {
        super('neural_net', 'Neural Bot');
        this.model = null;
        this.vocabulary = new Map();
    }

    async initialize() {
        await super.initialize();
        
        if (!this.model) {
            this.model = tf.sequential({
                layers: [
                    tf.layers.dense({ units: 32, activation: 'relu', inputShape: [50] }),
                    tf.layers.dense({ units: 16, activation: 'relu' }),
                    tf.layers.dense({ units: 32, activation: 'relu' }),
                    tf.layers.dense({ units: 50, activation: 'softmax' })
                ]
            });

            this.model.compile({
                optimizer: 'adam',
                loss: 'categoricalCrossentropy'
            });
        }
    }

    async generateResponse(input) {
        return "Using neural networks to learn response patterns...";
    }
}

class ChatInterface {
    constructor() {
        this.bots = new Map();
        this.selectedBots = new Set();
        this.initialize();
    }

    async initialize() {
        this.bots.set('pattern_matcher', new PatternBot());
        this.bots.set('markov_chain', new MarkovBot());
        this.bots.set('similarity_learner', new SimilarityBot());
        this.bots.set('neural_net', new NeuralBot());
        this.bots.set('simple_ai', new SimpleAI());

        for (const bot of this.bots.values()) {
            await bot.initialize();
        }

        await this.loadBotList();
        this.selectedBots.add('simple_ai');
        this.updateBotList();
    }

    async loadBotList() {
        const formData = new FormData();
        formData.append('action', 'get_bots');
        
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            this.updateBotListUI(data.bots);
        } catch (error) {
            console.error('Error loading bot list:', error);
        }
    }

    updateBotListUI(bots) {
        const botList = document.getElementById('botList');
        const controlsDiv = botList.querySelector('.bot-controls');
        const botsContainer = document.createElement('div');
        
        bots.forEach(bot => {
            const botDiv = document.createElement('div');
            botDiv.classList.add('bot-item');
            botDiv.innerHTML = `
                <input type="checkbox" 
                       class="bot-checkbox" 
                       id="bot-${bot.id}" 
                       value="${bot.id}" 
                       ${this.selectedBots.has(bot.id) ? 'checked' : ''}>
                <label for="bot-${bot.id}">
                    <strong>${bot.name}</strong><br>
                    <small>${bot.description}</small>
                </label>
            `;
            
            const checkbox = botDiv.querySelector('input');
            checkbox.addEventListener('change', () => this.toggleBot(bot.id));
            
            botsContainer.appendChild(botDiv);
        });
        
        const oldBotsContainer = botList.querySelector('.bots-container');
        if (oldBotsContainer) {
            oldBotsContainer.remove();
        }
        
        botsContainer.classList.add('bots-container');
        botList.appendChild(botsContainer);
    }

    toggleBot(botId) {
        if (this.selectedBots.has(botId)) {
            this.selectedBots.delete(botId);
        } else {
            this.selectedBots.add(botId);
        }
    }

    toggleAllBots(select) {
        const checkboxes = document.querySelectorAll('.bot-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = select;
            if (select) {
                this.selectedBots.add(checkbox.value);
            } else {
                this.selectedBots.delete(checkbox.value);
            }
        });
    }

    async sendMessage(input) {
        const responses = [];
        
        for (const botId of this.selectedBots) {
            const bot = this.bots.get(botId);
            if (bot) {
                try {
                    const response = await bot.generateResponse(input);
                    await bot.saveResponse(input, response);
                    responses.push({ botId, botName: bot.name, response });
                } catch (error) {
                    console.error(`Error with bot ${botId}:`, error);
                    responses.push({ 
                        botId, 
                        botName: bot.name, 
                        response: "Sorry, I encountered an error." 
                    });
                }
            }
        }
        
        return responses;
    }
}

const chatMessages = document.getElementById('chatMessages');
const userInput = document.getElementById('userInput');
const chatInterface = new ChatInterface();

async function sendMessage() {
    const input = userInput.value.trim();
    if (!input) return;

    addMessage(input, 'user');
    userInput.value = '';

    const responses = await chatInterface.sendMessage(input);
    responses.forEach(({ botName, response }) => {
        addMessage(response, 'ai', botName);
    });
}

function addMessage(text, sender, botName = '') {
    const messageDiv = document.createElement('div');
    messageDiv.classList.add('message', `${sender}-message`);
    
    if (botName) {
        const nameDiv = document.createElement('div');
        nameDiv.classList.add('bot-name');
        nameDiv.textContent = botName;
        messageDiv.appendChild(nameDiv);
    }
    
    const textDiv = document.createElement('div');
    textDiv.textContent = text;
    messageDiv.appendChild(textDiv);
    
    chatMessages.appendChild(messageDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function toggleAllBots(select) {
    chatInterface.toggleAllBots(select);
}

userInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        sendMessage();
    }
});
    </script>
</body>
</html>