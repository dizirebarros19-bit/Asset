// Preload animation frames to RAM/GPU
const animationFrames = ['3.png', '4.png', '5.png', 'sleeping.png'];
const preloadedImages = {};

animationFrames.forEach(src => {
    const img = new Image();
    img.src = src;
    preloadedImages[src] = img; // Keep reference to prevent garbage collection
});

let isBubbleHovered = false;

let femaleVoice = null;
let animationInterval = null;
let reviewTimeout = null;
let lastFullContent = "";
let anomalyQueue = [];
let isAnomalyActive = false;
let isSystemPoweredOn = true;

/** 1. Voice Selection **/
function loadVoices() {
    const voices = window.speechSynthesis.getVoices();
    const targets = ['Google US English', 'Microsoft Zira', 'Samantha', 'Aria'];
    femaleVoice = voices.find(v => targets.some(name => v.name.includes(name))) || 
                  voices.find(v => v.lang.includes('en') && v.name.toLowerCase().includes('female'));
}
if (window.speechSynthesis.onvoiceschanged !== undefined) window.speechSynthesis.onvoiceschanged = loadVoices;
loadVoices();

/** Show Bubble **/
function showBubble(content, force=false) {
    if (!isSystemPoweredOn) return;
    const bubble = document.getElementById('velyn-bubble');
    const display = document.getElementById('bubble-content');

    if (content) {
        display.innerHTML = content;
        lastFullContent = content;
    }

    bubble.style.display = 'block';
    setTimeout(() => bubble.classList.add('show'), 20);

    if (reviewTimeout) clearTimeout(reviewTimeout);
    if (!force) {
        reviewTimeout = setTimeout(() => {
            if (!isBubbleHovered) hideBubble();
        }, 10000);
    }
}

/** Hide Bubble **/
function hideBubble(force=false) {
    const bubble = document.getElementById('velyn-bubble');
    if (force) isBubbleHovered = false;

    bubble.classList.remove('show');
    setTimeout(() => {
        if (!isBubbleHovered) bubble.style.display = 'none';
    }, 400); // match CSS transition
}





/** 3. Avatar Animations **/
/** 3. Avatar Animations - Optimized for Web **/
function startTalkingAnimation() {
    const face = document.getElementById('ai-face');
    if (!face) return;

    let toggle = true;
    if (animationInterval) clearInterval(animationInterval);
    
    const baseFrame = isAnomalyActive ? '4.png' : '3.png';
    const talkFrame = '5.png';

    animationInterval = setInterval(() => {
        // Only update if the source actually needs to change
        const nextSrc = toggle ? talkFrame : baseFrame;
        if (face.getAttribute('src') !== nextSrc) {
            face.src = nextSrc;
        }
        toggle = !toggle;
    }, 160); // Slightly faster (160ms) often feels smoother for 2-frame lip sync
}

function stopTalkingAnimation() {
    if (animationInterval) clearInterval(animationInterval);
    animationInterval = null;
    
    const face = document.getElementById('ai-face');
    const finalFrame = isAnomalyActive ? '4.png' : '3.png';
    
    if (face && face.getAttribute('src') !== finalFrame) {
        face.src = finalFrame;
    }
}

/** 4. Speech Engine **/
function speak(text, callback) {
    if (!isSystemPoweredOn) return;
    window.speechSynthesis.cancel();
    const cleanText = text.replace(/<\/?[^>]+(>|$)/g, "");
    const utterance = new SpeechSynthesisUtterance(cleanText);
    if (femaleVoice) utterance.voice = femaleVoice;
    utterance.pitch = 1.2;
    utterance.rate = 1.0;
    utterance.onstart = () => startTalkingAnimation();
    utterance.onend = () => {
        stopTalkingAnimation();
        hideBubble();
        if (callback) callback();
        processAnomalyQueue();
    };
    window.speechSynthesis.speak(utterance);
}

/** 5. Anomaly Queue (ML Thoughts Integrated) **/
function enqueueAnomaly(assetId, message, dbId, thoughts) {
    anomalyQueue.push({assetId, message, dbId, thoughts});
}



function processAnomalyQueue() {
    if (!isSystemPoweredOn || window.speechSynthesis.speaking) return;
    
    if (anomalyQueue.length === 0) return;

    const {assetId, message, dbId, thoughts} = anomalyQueue.shift();
    isAnomalyActive = true;

    const aiDot = document.getElementById('ai-dot');
    if (aiDot) aiDot.classList.add('status-alert');
    document.getElementById('ai-face').src = '4.png';

 // Create content with View Asset button
   const content = `
        <div class="notif-card">
            <!-- Modernized Velyn Intelligence -->
<span class="notif-badge" style="
    font-family: 'Orbitron', sans-serif;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    background: linear-gradient(90deg, #0f766e, #14b8a6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
">
    Velyn Intelligence
</span>

<!-- Google Font import -->
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;600&display=swap" rel="stylesheet">

          <p style="
    font-family: 'Inter', 'Poppins', system-ui, sans-serif;
    font-style: normal;
    font-weight: 500;
    letter-spacing: 0.02em;
    color: #475569;
    font-size: 12px;
    margin: 8px 0;
    line-height: 1.5;
">
    "${thoughts}"
</p>

            <hr style="border:0; border-top:1px solid #eee;">
         <strong style="
    font-family: 'Inter', 'Poppins', system-ui, sans-serif;
    font-weight: 600;
    letter-spacing: 0.03em;
    color: #1e293b;
    font-size: 14px;
">
    Asset Anomaly: ${assetId}
</strong>
       <button class="btn-view-history" 
   onclick="window.location.href='ai.php?db_id=${dbId}&asset_id=${assetId}'"
    style="
        background-color: #1E293B; 
        color: #FFFFFF; 
        border: none; 
        padding: 8px 16px; 
        border-radius: 6px; 
        font-size: 12px; 
        font-weight: 600; 
        font-family: 'Inter', sans-serif;
        cursor: pointer;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        transition: background-color 0.2s ease, transform 0.1s ease;
        outline: none;">
    View Detail
</button>
        </div>
    `;
    showBubble(content, true);

    // Velyn speaks her thoughts
    speak(thoughts, () => {
        isAnomalyActive = false;
        if (aiDot) aiDot.classList.remove('status-alert');
    });
}




/** 6. Show Anomaly Notification with Cooldown & Queue Check **/
function showAnomalyNotif(assetId, message, dbId, thoughts) {
    // Only prevent duplicates in queue
    const isAlreadyQueued = anomalyQueue.some(a => a.assetId === assetId && a.dbId === dbId);
    if (isAlreadyQueued) return;

    enqueueAnomaly(assetId, message, dbId, thoughts);
    processAnomalyQueue();
}


/** 7. Event Listeners (Head Click Toggle) **/
const aiFace = document.getElementById('ai-face');
const velynBubble = document.getElementById('velyn-bubble');


if (aiFace) {
    aiFace.addEventListener('click', () => {
        // 1. If Velyn is sleeping, wake her up immediately
        if (!isSystemPoweredOn) {
            wakeUpVelyn();
            return;
        }

        // 2. If the menu is already open, clicking the head closes it
        const velynBubble = document.getElementById('velyn-bubble');
        if (velynBubble.classList.contains('show')) {
            hideBubble();
            return;
        }

        // 3. Otherwise, show the System Protocol menu
        const menuContent = `
            <div style="border-bottom: 1px solid #f1f5f9; padding-bottom: 8px; margin-bottom: 4px;">
                <span style="font-family:'Orbitron',sans-serif; font-size:11px; color:#0f766e; letter-spacing:1px; font-weight:700;">
                    Velyn Intelligence
                </span>
            </div>
            <div class="ai-menu-container">
              <button class="btn-ai-menu btn-anomalies" onclick="openAuditPanel()">
                <span>Neural Network</span>
                <i class="fas fa-chevron-right" style="font-size:10px; opacity:0.7;"></i>
              </button>

              <button class="btn-ai-menu btn-sleep" onclick="sleepVelyn()">
                  <span>Sleep Mode</span>
                  <i class="fas fa-power-off" style="font-size:10px;"></i>
              </button>
            </div>
        `;
        showBubble(menuContent, false); 
    });
}


function openAuditPanel() {
    if (!isSystemPoweredOn) return;

    // 1. Fully pause AI systems
    isSystemPoweredOn = false;
    window.speechSynthesis.cancel();
    if (animationInterval) clearInterval(animationInterval);

    // 2. Hide assistant visually
    const aiAssistant = document.querySelector('.ai-assistant');
    if (aiAssistant) aiAssistant.style.display = 'none';

    // 3. Show panel and overlay
    const panel = document.getElementById('audit-panel');
    const overlay = document.getElementById('audit-overlay');
    if (panel && overlay) {
        panel.classList.add('open');
        overlay.classList.add('open');
        panel.style.zIndex = 1001;
        overlay.style.zIndex = 1000;
    }

    console.log("AI system paused while audit panel is open.");
}

function closeAuditPanel() {
    // 1. Restore AI systems
    isSystemPoweredOn = true;

    const aiDot = document.getElementById('ai-dot');
    const aiFace = document.getElementById('ai-face');
    if (aiDot) aiDot.style.background = '#22c55e';
    if (aiFace) aiFace.src = '3.png';

    // 2. Restore assistant visibility
    const aiAssistant = document.querySelector('.ai-assistant');
    if (aiAssistant) aiAssistant.style.display = 'flex'; // restore flex layout

    // 3. Hide panel and overlay
    const panel = document.getElementById('audit-panel');
    const overlay = document.getElementById('audit-overlay');
    if (panel && overlay) {
        panel.classList.remove('open');
        overlay.classList.remove('open');
    }
}



function sleepVelyn() {
    isSystemPoweredOn = false;
    // SAVE STATE: Store as a string 'false'
    localStorage.setItem('velyn_powered_on', 'false');

    window.speechSynthesis.cancel();
    if (animationInterval) clearInterval(animationInterval);
    
    hideBubble(true);

    const aiDot = document.getElementById('ai-dot');
    const aiFace = document.getElementById('ai-face');
    
    if (aiDot) { 
        aiDot.style.background = '#94a3b8'; 
        aiDot.classList.remove('status-alert');
    }
    if (aiFace) aiFace.src = 'sleeping.png';
}

function wakeUpVelyn() {
    isSystemPoweredOn = true;
    // SAVE STATE: Store as a string 'true'
    localStorage.setItem('velyn_powered_on', 'true');

    const aiDot = document.getElementById('ai-dot');
    const aiFace = document.getElementById('ai-face');
    
    if (aiDot) aiDot.style.background = '#22c55e';
    if (aiFace) aiFace.src = '3.png';
    
    showBubble("Neural systems initialized. Monitoring active.", true);
    speak("Velyn is back online.");
}


/** 8. Neural Scan - Robust Version **/
async function neuralScan() {
    if (!isSystemPoweredOn) {
        setTimeout(neuralScan, 10000); // Check again in 10s if we wake up
        return;
    }

    try {
         const res = await fetch('http://127.0.0.1:5000/scan?type=standby');
        const data = await res.json();
        
        if (data.critical && data.anomaly) {
            const a = data.anomaly;
            showAnomalyNotif(a.asset_id, a.summary, a.id, a.thoughts);
        }
    } catch (e) { 
        console.error("Scan Error:", e); 
    } finally {
        // Wait 10 seconds AFTER the request finishes before starting the next one
        setTimeout(neuralScan, 10000);
    }
}


/** 9. Init **/

/** 9. Init - Improved Event Handling **/
window.addEventListener('load', () => {
    const savedState = localStorage.getItem('velyn_powered_on');
    
    if (savedState === 'false') {
        isSystemPoweredOn = false;
        const aiDot = document.getElementById('ai-dot');
        const aiFace = document.getElementById('ai-face');
        if (aiDot) aiDot.style.background = '#94a3b8';
        if (aiFace) aiFace.src = 'sleeping.png';
    } else {
        isSystemPoweredOn = true;
    }

    // Start the recursive scan
    neuralScan();

    const bubble = document.getElementById('velyn-bubble');
    if (bubble) {
        bubble.addEventListener('mouseenter', () => {
            isBubbleHovered = true;
            if (reviewTimeout) clearTimeout(reviewTimeout);
        });

        bubble.addEventListener('mouseleave', () => {
            isBubbleHovered = false;
            reviewTimeout = setTimeout(() => {
                if (!isBubbleHovered) hideBubble();
            }, 5000);
        });
    }
});



