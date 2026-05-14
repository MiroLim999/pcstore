/* ============================================================
   BUILDER.JS – PC Builder (DB-backed version)
   Fetches catalog from /api/catalog.php, keeps all original
   interactivity: drag, connectors, compat, bottleneck, FPS.
   ============================================================ */

// ─── CONFIG ───────────────────────────────────────────────────
const CURRENCY = (typeof APP_CONFIG !== 'undefined') ? APP_CONFIG.currency : '₱';
const CURRENCY_CODE = (typeof APP_CONFIG !== 'undefined') ? APP_CONFIG.currencyCode : 'PHP';
function formatPrice(price) { return price === 0 ? 'FREE' : CURRENCY + price.toLocaleString(); }

// ─── DATA (populated from API) ────────────────────────────────
let CATALOG = {};
let OPTION_META = {};
const CPU_TIER = {};
const GPU_TIER = {};
const CPU_GAMING_SCORE = {};
const GPU_GAMING_SCORE = {};
const GPU_BASE_FPS = {};
let MAX_CPU_SCORE = 100;
let MAX_GPU_SCORE = 100;

const SLUG_TO_CARD = { cpu:'card-cpu', cooler:'card-cooler', mobo:'card-mobo', ram:'card-ram', gpu:'card-gpu', ssd:'card-ssd', psu:'card-psu', case:'card-case' };
const CARD_TO_SLUG = Object.fromEntries(Object.entries(SLUG_TO_CARD).map(([k,v])=>[v,k]));

const CARD_ICONS = {
  'card-cpu':'<svg viewBox="0 0 20 20" fill="none"><rect x="5" y="5" width="10" height="10" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M8 2v3M12 2v3M8 15v3M12 15v3M2 8h3M2 12h3M15 8h3M15 12h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
  'card-cooler':'<svg viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="1.5"/><path d="M10 6v4l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
  'card-mobo':'<svg viewBox="0 0 20 20" fill="none"><rect x="3" y="3" width="14" height="14" rx="2" stroke="currentColor" stroke-width="1.5"/><rect x="7" y="7" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.3"/></svg>',
  'card-ram':'<svg viewBox="0 0 20 20" fill="none"><rect x="3" y="7" width="14" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M7 7V5M10 7V5M13 7V5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
  'card-gpu':'<svg viewBox="0 0 20 20" fill="none"><rect x="2" y="6" width="16" height="8" rx="1.5" stroke="currentColor" stroke-width="1.5"/><circle cx="13" cy="10" r="2" stroke="currentColor" stroke-width="1.3"/><circle cx="8" cy="10" r="2" stroke="currentColor" stroke-width="1.3"/></svg>',
  'card-ssd':'<svg viewBox="0 0 20 20" fill="none"><rect x="3" y="6" width="14" height="8" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M7 10h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
  'card-psu':'<svg viewBox="0 0 20 20" fill="none"><path d="M10 3L5 10h5l-1 7 6-8h-5l1-6z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>',
  'card-case':'<svg viewBox="0 0 20 20" fill="none"><rect x="5" y="2" width="10" height="16" rx="2" stroke="currentColor" stroke-width="1.5"/><circle cx="10" cy="13" r="1.5" stroke="currentColor" stroke-width="1.2"/><path d="M8 6h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>'
};

const CARD_EMPTY_COPY = {
  "card-cpu":{name:"No processor selected",hint:"Click add to choose a CPU.",button:"Add Processor"},
  "card-cooler":{name:"No cooler selected",hint:"Click add to choose a cooler.",button:"Add CPU Cooler"},
  "card-mobo":{name:"No motherboard selected",hint:"Click add to choose a motherboard.",button:"Add Motherboard"},
  "card-ram":{name:"No memory selected",hint:"Click add to choose RAM.",button:"Add Memory"},
  "card-gpu":{name:"No graphics card selected",hint:"Click add to choose a GPU.",button:"Add Graphics Card"},
  "card-ssd":{name:"No storage selected",hint:"Click add to choose storage.",button:"Add Storage"},
  "card-psu":{name:"No power supply selected",hint:"Click add to choose a PSU.",button:"Add Power Supply"},
  "card-case":{name:"No case selected",hint:"Click add to choose a case.",button:"Add Case"},
};

const selections = {"card-cpu":null,"card-cooler":null,"card-mobo":null,"card-ram":null,"card-gpu":null,"card-ssd":null,"card-psu":null,"card-case":null};

const CARD_IDS = ["card-cpu","card-cooler","card-mobo","card-ram","card-gpu","card-ssd","card-psu","card-case"];

const FPS_GAMES = [
  {name:"Valorant",settings:"1080p High",multiplier:3.2,cpuHeavy:true},
  {name:"CS2",settings:"1080p High",multiplier:2.4,cpuHeavy:true},
  {name:"Fortnite",settings:"1080p High",multiplier:1.5},
  {name:"GTA V",settings:"1080p High",multiplier:1.8},
  {name:"Cyberpunk 2077",settings:"1080p High",multiplier:0.75},
  {name:"Call of Duty: MW3",settings:"1080p High",multiplier:1.1},
];

// ─── API LOADER ───────────────────────────────────────────────
async function loadCatalogFromAPI() {
  try {
    const url = (typeof APP_CONFIG !== 'undefined') ? APP_CONFIG.apiCatalog : '/pcstore/api/catalog.php';
    const res = await fetch(url);
    const data = await res.json();
    if (!data.success) { console.error('Catalog load failed:', data); return; }
    for (const [slug, catData] of Object.entries(data.catalog)) {
      const cardId = SLUG_TO_CARD[slug];
      if (!cardId) continue;
      CATALOG[cardId] = {
        label: catData.label.toUpperCase(),
        title: 'Choose a ' + catData.label,
        icon: CARD_ICONS[cardId] || '',
        options: catData.options.map(opt => ({
          id: String(opt.id), name: opt.name, specs: opt.specs || [],
          price: opt.price, image: opt.image || '', stock: opt.stock || 0,
        }))
      };
      for (const opt of catData.options) {
        const meta = {}; const c = opt.compat || {};
        if (c.socket) meta.socket = c.socket;
        if (c.tdp) meta.tdp = parseInt(c.tdp);
        if (c.cores) meta.cores = parseInt(c.cores);
        if (c.threads) meta.threads = parseInt(c.threads);
        if (c.boost_clock) meta.boost = parseFloat(c.boost_clock);
        if (c.supported_sockets) { try { meta.sockets = JSON.parse(c.supported_sockets); } catch(e) { meta.sockets = []; } }
        if (c.max_tdp) meta.maxTdp = parseInt(c.max_tdp);
        if (c.form_factor) meta.formFactor = c.form_factor;
        if (c.memory_type) meta.memoryType = c.memory_type;
        if (c.m2_slots !== undefined && c.m2_slots !== null) meta.m2Slots = parseInt(c.m2_slots);
        if (c.max_memory_speed) meta.maxMemorySpeed = parseInt(c.max_memory_speed);
        if (c.memory_speed) meta.speed = parseInt(c.memory_speed);
        if (c.memory_capacity) meta.capacity = parseInt(c.memory_capacity);
        if (c.memory_type && !meta.memoryType) meta.memoryType = c.memory_type;
        if (c.storage_interface) meta.interface = c.storage_interface;
        if (c.storage_form_factor && !meta.formFactor) meta.formFactor = c.storage_form_factor;
        if (c.requires_m2 !== undefined) meta.requiresM2 = parseInt(c.requires_m2) === 1;
        if (c.wattage) meta.wattage = parseInt(c.wattage);
        if (c.supported_form_factors) { try { meta.supportedFormFactors = JSON.parse(c.supported_form_factors); } catch(e) { meta.supportedFormFactors = []; } }
        if (c.gaming_tier) { if (slug==='cpu') CPU_TIER[String(opt.id)]=parseInt(c.gaming_tier); if (slug==='gpu') GPU_TIER[String(opt.id)]=parseInt(c.gaming_tier); }
        if (c.gaming_score) { if (slug==='cpu') CPU_GAMING_SCORE[String(opt.id)]=parseInt(c.gaming_score); if (slug==='gpu') GPU_GAMING_SCORE[String(opt.id)]=parseInt(c.gaming_score); }
        if (c.base_fps && slug==='gpu') GPU_BASE_FPS[String(opt.id)] = parseInt(c.base_fps);
        OPTION_META[String(opt.id)] = meta;
      }
    }
    const cs = Object.values(CPU_GAMING_SCORE), gs = Object.values(GPU_GAMING_SCORE);
    if (cs.length) MAX_CPU_SCORE = Math.max(...cs);
    if (gs.length) MAX_GPU_SCORE = Math.max(...gs);
    renderAllCards(); updateTotalPrice(); updateBuildPanels();
  } catch (err) { console.error('Failed to load catalog:', err); }
}

// ─── HELPERS ──────────────────────────────────────────────────
function getOptionMeta(option) { if (!option) return {}; return OPTION_META[option.id] || {}; }
function getSelectedOption(cardId) { const id = selections[cardId]; if (!id) return null; return CATALOG[cardId]?.options.find(o => o.id === id) || null; }
function estimateWattage(cpu, gpu) { const ct = (getOptionMeta(cpu).tdp||0), gt = (getOptionMeta(gpu).tdp||0); if (!ct&&!gt) return 0; return Math.round((ct+gt+60)*1.3); }

function detectBottleneck(cpu, gpu) {
  if (!cpu||!gpu) return null;
  const c=CPU_TIER[cpu.id], g=GPU_TIER[gpu.id]; if (!c||!g) return null;
  if (g-c>=2) return "gpu"; if (c-g>=2) return "cpu"; return null;
}

function calculateBottleneck(cpu, gpu) {
  if (!cpu||!gpu) return null;
  const cs=CPU_GAMING_SCORE[cpu.id], gs=GPU_GAMING_SCORE[gpu.id]; if (!cs||!gs) return null;
  const stronger=Math.max(cs,gs), weaker=Math.min(cs,gs);
  const pct=Math.round(((stronger-weaker)/stronger)*100);
  let limitedBy="none"; if (cs<gs) limitedBy="cpu"; else if (gs<cs) limitedBy="gpu";
  let severity;
  if (pct<=5) severity="excellent"; else if (pct<=12) severity="balanced";
  else if (pct<=20) severity=(limitedBy==="cpu")?"moderate":"minor";
  else if (pct<=35) severity=(limitedBy==="cpu")?"severe":"moderate";
  else severity="severe";
  return {pct, limitedBy, severity, cpuStrength:Math.round((cs/MAX_CPU_SCORE)*100), gpuStrength:Math.round((gs/MAX_GPU_SCORE)*100)};
}

function estimateFps(cpu, gpu, game) {
  if (!gpu) return null; const baseFps=GPU_BASE_FPS[gpu.id]; if (!baseFps) return null;
  const gpuFps=baseFps*game.multiplier;
  const cpuScore=cpu?CPU_GAMING_SCORE[cpu.id]||80:80;
  const cpuCeiling=game.cpuHeavy?cpuScore*4.2*game.multiplier:cpuScore*2.2*game.multiplier;
  return Math.round(Math.min(gpuFps, cpuCeiling));
}

function getGamingTier(gpu) {
  if (!gpu) return {main:"Gaming Ready",sub:"Pick a GPU"};
  const t=GPU_TIER[gpu.id]||0;
  if (t>=5) return {main:"1440p Gaming",sub:"Ultra Settings"};
  if (t>=4) return {main:"1440p Gaming",sub:"High Settings"};
  if (t>=3) return {main:"1080p Gaming",sub:"High Settings"};
  return {main:"1080p Gaming",sub:"Medium Settings"};
}

// ─── SVG CONNECTORS ───────────────────────────────────────────
function drawConnectors() {
  const svg=document.getElementById("connector-svg"), wrapper=document.getElementById("diagram-wrapper");
  if (!svg||!wrapper) return; svg.innerHTML="";
  const pcImg=document.getElementById("pc-image"); if (!pcImg) return;
  const wrapRect=wrapper.getBoundingClientRect(), pcRect=pcImg.getBoundingClientRect();
  const cx=pcRect.left+pcRect.width/2-wrapRect.left, cy=pcRect.top+pcRect.height/2-wrapRect.top;
  CARD_IDS.forEach(id=>{
    const card=document.getElementById(id); if (!card) return;
    const cardRect=card.getBoundingClientRect(), dot=card.dataset.dot;
    let x1,y1;
    if (dot==="right"){x1=cardRect.right-wrapRect.left;y1=cardRect.top+cardRect.height/2-wrapRect.top;}
    else{x1=cardRect.left-wrapRect.left;y1=cardRect.top+cardRect.height/2-wrapRect.top;}
    const dx=cx-x1, cp1x=x1+dx*0.45, cp2x=cx-dx*0.45;
    const path=document.createElementNS("http://www.w3.org/2000/svg","path");
    path.setAttribute("d",`M ${x1} ${y1} C ${cp1x} ${y1} ${cp2x} ${cy} ${cx} ${cy}`);
    path.setAttribute("class","connector-line");
    path.style.animationDelay=`${Math.random()*1.2}s`;
    svg.appendChild(path);
  });
}
window.addEventListener("load",()=>{drawConnectors();});
window.addEventListener("resize",()=>{clearTimeout(window._resizeTimer);window._resizeTimer=setTimeout(drawConnectors,120);});
document.fonts?.ready?.then(drawConnectors);
window.addEventListener("load",()=>setTimeout(drawConnectors,300));

// ─── CARD HOVER HIGHLIGHT ─────────────────────────────────────
function setupCardHighlight(){
  CARD_IDS.forEach((id,i)=>{const card=document.getElementById(id);if(!card)return;
    card.addEventListener("mouseenter",()=>{document.querySelectorAll(".connector-line").forEach((ln,j)=>{if(j===i){ln.style.opacity="1";ln.style.strokeWidth="2.5";ln.style.filter="drop-shadow(0 0 4px rgba(108,71,255,0.7))";}else{ln.style.opacity="0.15";}});});
    card.addEventListener("mouseleave",()=>{document.querySelectorAll(".connector-line").forEach(ln=>{ln.style.opacity="0.45";ln.style.strokeWidth="1.5";ln.style.filter="";});});
  });
}
window.addEventListener("load",setupCardHighlight);

// ─── TOAST ────────────────────────────────────────────────────
function showToast(msg){const t=document.getElementById("toast");if(!t)return;t.textContent=msg;t.classList.add("show");setTimeout(()=>t.classList.remove("show"),2800);}

// ─── PRICE ANIMATION ──────────────────────────────────────────
function animatePrice(el,target,duration=1200){
  let start=null;
  const step=(ts)=>{if(!start)start=ts;const p=Math.min((ts-start)/duration,1);const eased=1-Math.pow(1-p,3);
    const v=Math.round(eased*target).toLocaleString();
    el.innerHTML=`<span class="currency">${CURRENCY}</span>${v} <span class="usd">${CURRENCY_CODE}</span>`;
    if(p<1)requestAnimationFrame(step);};
  requestAnimationFrame(step);
}

// ─── DRAG AND DROP ────────────────────────────────────────────
function initDragAndDrop(){
  const cards=document.querySelectorAll(".component-card");
  let activeCard=null,pendingCard=null,holdTimer=null,startX=0,startY=0,mouseDownX=0,mouseDownY=0,isDragging=false;
  const HOLD_DELAY=180,MOVE_THRESHOLD=6;
  const INITIAL_POSITIONS={"card-cpu":{x:-2,y:-70},"card-cooler":{x:-141,y:-67},"card-mobo":{x:-113,y:-59},"card-ram":{x:-36,y:-61},"card-gpu":{x:2,y:-70},"card-ssd":{x:141,y:-67},"card-psu":{x:113,y:-59},"card-case":{x:36,y:-61}};
  cards.forEach(card=>{card.removeAttribute("draggable");const ip=INITIAL_POSITIONS[card.id]||{x:0,y:0};card._tx=ip.x;card._ty=ip.y;card.style.setProperty("--tx",`${card._tx}px`);card.style.setProperty("--ty",`${card._ty}px`);
    card.addEventListener("mousedown",e=>{if(e.target.closest("button")||e.target.closest("a"))return;e.preventDefault();pendingCard=card;mouseDownX=e.clientX;mouseDownY=e.clientY;isDragging=false;card.classList.add("hold-pending");holdTimer=setTimeout(()=>activateDrag(card,e.clientX,e.clientY),HOLD_DELAY);});
  });
  function activateDrag(card,cx,cy){activeCard=card;isDragging=true;startX=cx-activeCard._tx;startY=cy-activeCard._ty;card.classList.remove("hold-pending");card.classList.add("dragging");card.style.transition="none";card.style.animation="none";document.body.style.userSelect="none";document.body.style.cursor="grabbing";}
  window.addEventListener("mousemove",e=>{if(pendingCard&&!isDragging){const dx=e.clientX-mouseDownX,dy=e.clientY-mouseDownY;if(Math.sqrt(dx*dx+dy*dy)>=MOVE_THRESHOLD){clearTimeout(holdTimer);activateDrag(pendingCard,mouseDownX,mouseDownY);}}if(!activeCard||!isDragging)return;activeCard._tx=e.clientX-startX;activeCard._ty=e.clientY-startY;activeCard.style.setProperty("--tx",`${activeCard._tx}px`);activeCard.style.setProperty("--ty",`${activeCard._ty}px`);const pcImg=document.getElementById("pc-image");if(pcImg){const r=activeCard.getBoundingClientRect(),pr=pcImg.getBoundingClientRect();activeCard.setAttribute("data-dot",r.left+r.width/2<pr.left+pr.width/2?"right":"left");}drawConnectors();});
  window.addEventListener("mouseup",()=>{clearTimeout(holdTimer);const wasClick=!!pendingCard&&!isDragging;const clickTarget=pendingCard;if(pendingCard){pendingCard.classList.remove("hold-pending");pendingCard=null;}if(activeCard){activeCard.style.transition="";activeCard.classList.remove("dragging");document.body.style.userSelect="";document.body.style.cursor="";activeCard=null;isDragging=false;drawConnectors();}if(wasClick&&clickTarget)openComponentModal(clickTarget);});
}
window.addEventListener("load",initDragAndDrop);

// ─── RENDER CARDS ─────────────────────────────────────────────
function renderCard(cardId){
  const card=document.getElementById(cardId);if(!card)return;
  const opt=getSelectedOption(cardId);const img=card.querySelector(".card-img");const nameEl=card.querySelector(".card-name");const specsEl=card.querySelector(".card-specs");const addBtn=card.querySelector(".card-add-btn");const emptyCopy=CARD_EMPTY_COPY[cardId];
  let actionRow=card.querySelector(".card-actions");if(!actionRow&&addBtn){actionRow=document.createElement("div");actionRow.className="card-actions";addBtn.insertAdjacentElement("beforebegin",actionRow);actionRow.appendChild(addBtn);}
  let removeBtn=card.querySelector(".card-remove-btn");
  if(!opt){card.classList.add("is-empty");if(nameEl)nameEl.textContent=emptyCopy?.name||"";if(specsEl)specsEl.innerHTML=`<li>${emptyCopy?.hint||""}</li>`;if(addBtn)addBtn.textContent=emptyCopy?.button||"Add";if(removeBtn)removeBtn.remove();card.removeAttribute("data-price");return;}
  card.classList.remove("is-empty");
  if(img&&opt.image){img.src=opt.image;img.alt=opt.name;}
  if(nameEl)nameEl.innerHTML=`${opt.name} <span class="card-price-inline">${formatPrice(opt.price)}</span>`;
  if(specsEl)specsEl.innerHTML=opt.specs.map(s=>`<li>${s}</li>`).join("");
  if(addBtn)addBtn.textContent="Change";
  card.setAttribute("data-price",opt.price);
  if(!removeBtn&&actionRow){removeBtn=document.createElement("button");removeBtn.type="button";removeBtn.className="card-remove-btn";removeBtn.textContent="✕ Remove";removeBtn.addEventListener("click",e=>{e.stopPropagation();selections[cardId]=null;renderAllCards();updateTotalPrice();updateBuildPanels();showToast("Part removed.");});actionRow.appendChild(removeBtn);}
}
function renderAllCards(){CARD_IDS.forEach(renderCard);}

// ─── COMPATIBILITY ────────────────────────────────────────────
function getOptionCompatibility(cardId, option){
  const cpu=cardId==="card-cpu"?option:getSelectedOption("card-cpu"),cooler=cardId==="card-cooler"?option:getSelectedOption("card-cooler"),mobo=cardId==="card-mobo"?option:getSelectedOption("card-mobo"),ram=cardId==="card-ram"?option:getSelectedOption("card-ram"),gpu=cardId==="card-gpu"?option:getSelectedOption("card-gpu"),storage=cardId==="card-ssd"?option:getSelectedOption("card-ssd"),psu=cardId==="card-psu"?option:getSelectedOption("card-psu"),pcCase=cardId==="card-case"?option:getSelectedOption("card-case");
  const errors=[],warnings=[];
  const cpuM=getOptionMeta(cpu),coolerM=getOptionMeta(cooler),moboM=getOptionMeta(mobo),ramM=getOptionMeta(ram),gpuM=getOptionMeta(gpu),storageM=getOptionMeta(storage),psuM=getOptionMeta(psu),caseM=getOptionMeta(pcCase);
  if(cardId==="card-cpu"){if(mobo&&cpuM.socket&&moboM.socket&&cpuM.socket!==moboM.socket)errors.push("Socket mismatch with motherboard.");if(cooler&&coolerM.sockets&&cpuM.socket&&!coolerM.sockets.includes(cpuM.socket))errors.push("Cooler does not support CPU socket.");if(cooler&&coolerM.maxTdp&&cpuM.tdp&&coolerM.maxTdp<cpuM.tdp)errors.push("Cooler TDP is too low.");if(psu&&psuM.wattage&&estimateWattage(cpu,gpu)>psuM.wattage)warnings.push("PSU wattage may be low.");}
  if(cardId==="card-cooler"){if(cpu&&coolerM.sockets&&cpuM.socket&&!coolerM.sockets.includes(cpuM.socket))errors.push("Cooler does not support CPU socket.");if(cpu&&coolerM.maxTdp&&cpuM.tdp&&coolerM.maxTdp<cpuM.tdp)errors.push("Cooler TDP is too low.");}
  if(cardId==="card-mobo"){if(cpu&&cpuM.socket&&moboM.socket&&cpuM.socket!==moboM.socket)errors.push("Socket mismatch with CPU.");if(ram&&ramM.memoryType&&moboM.memoryType&&ramM.memoryType!==moboM.memoryType)errors.push("Memory type mismatch.");if(ram&&ramM.speed&&moboM.maxMemorySpeed&&ramM.speed>moboM.maxMemorySpeed)warnings.push("RAM speed above board rating.");if(pcCase&&caseM.supportedFormFactors&&moboM.formFactor&&!caseM.supportedFormFactors.includes(moboM.formFactor))errors.push("Case does not fit motherboard.");if(storage&&storageM.requiresM2&&moboM.m2Slots===0)errors.push("No M.2 slot for NVMe.");}
  if(cardId==="card-ram"){if(mobo&&ramM.memoryType&&moboM.memoryType&&ramM.memoryType!==moboM.memoryType)errors.push("Memory type mismatch.");if(mobo&&ramM.speed&&moboM.maxMemorySpeed&&ramM.speed>moboM.maxMemorySpeed)warnings.push("RAM speed above board rating.");}
  if(cardId==="card-gpu"){if(psu&&psuM.wattage&&estimateWattage(cpu,option)>psuM.wattage)warnings.push("PSU wattage may be low.");}
  if(cardId==="card-ssd"){if(mobo&&storageM.requiresM2&&moboM.m2Slots===0)errors.push("No M.2 slot for NVMe.");}
  if(cardId==="card-psu"){if(psuM.wattage&&estimateWattage(cpu,gpu)>psuM.wattage)warnings.push("Recommended wattage higher than PSU.");}
  if(cardId==="card-case"){if(mobo&&caseM.supportedFormFactors&&moboM.formFactor&&!caseM.supportedFormFactors.includes(moboM.formFactor))errors.push("Case does not fit motherboard.");}
  return {status:errors.length?"error":warnings.length?"warn":"ok",reasons:errors.length?errors:warnings};
}

function buildCompatibilityChecks(){
  const checks=[];const cpu=getSelectedOption("card-cpu"),cooler=getSelectedOption("card-cooler"),mobo=getSelectedOption("card-mobo"),ram=getSelectedOption("card-ram"),gpu=getSelectedOption("card-gpu"),storage=getSelectedOption("card-ssd"),psu=getSelectedOption("card-psu"),pcCase=getSelectedOption("card-case");
  const cpuM=getOptionMeta(cpu),coolerM=getOptionMeta(cooler),moboM=getOptionMeta(mobo),ramM=getOptionMeta(ram),storageM=getOptionMeta(storage),psuM=getOptionMeta(psu),caseM=getOptionMeta(pcCase);
  if(cpu&&mobo){checks.push(cpuM.socket===moboM.socket?{status:"ok",label:"CPU socket",detail:`${cpuM.socket} matches motherboard.`}:{status:"error",label:"CPU socket",detail:`${cpuM.socket} does not match ${moboM.socket}.`});}else{checks.push({status:"pending",label:"CPU socket",detail:"Select a CPU and motherboard."});}
  if(cpu&&cooler){if(!coolerM.sockets||!coolerM.sockets.includes(cpuM.socket))checks.push({status:"error",label:"Cooler support",detail:"Cooler does not fit CPU socket."});else if(coolerM.maxTdp&&cpuM.tdp&&coolerM.maxTdp<cpuM.tdp)checks.push({status:"error",label:"Cooler capacity",detail:"Cooler TDP is too low."});else checks.push({status:"ok",label:"Cooler support",detail:"Cooler supports CPU socket and TDP."});}else{checks.push({status:"pending",label:"Cooler support",detail:"Select a CPU and cooler."});}
  if(ram&&mobo){if(ramM.memoryType!==moboM.memoryType)checks.push({status:"error",label:"Memory type",detail:"RAM type mismatch."});else if(ramM.speed&&moboM.maxMemorySpeed&&ramM.speed>moboM.maxMemorySpeed)checks.push({status:"warn",label:"Memory speed",detail:"RAM speed above board rating."});else checks.push({status:"ok",label:"Memory type",detail:`${ramM.memoryType} compatible.`});}else{checks.push({status:"pending",label:"Memory type",detail:"Select RAM and motherboard."});}
  if(pcCase&&mobo){checks.push(caseM.supportedFormFactors&&caseM.supportedFormFactors.includes(moboM.formFactor)?{status:"ok",label:"Case fit",detail:`${moboM.formFactor} fits.`}:{status:"error",label:"Case fit",detail:"Case does not support motherboard size."});}else{checks.push({status:"pending",label:"Case fit",detail:"Select case and motherboard."});}
  if(storage&&mobo){checks.push(storageM.requiresM2&&moboM.m2Slots===0?{status:"error",label:"Storage",detail:"No M.2 slot."}:{status:"ok",label:"Storage",detail:`${storageM.interface||"SATA"} supported.`});}else{checks.push({status:"pending",label:"Storage",detail:"Select storage and motherboard."});}
  if(psu&&(cpu||gpu)){const req=estimateWattage(cpu,gpu);checks.push(psuM.wattage&&req&&psuM.wattage<req?{status:"warn",label:"Power",detail:`Need ${req}W, PSU is ${psuM.wattage}W.`}:{status:"ok",label:"Power",detail:"PSU wattage sufficient."});}else{checks.push({status:"pending",label:"Power",detail:"Select PSU + CPU/GPU."});}
  if(cpu&&gpu){const neck=detectBottleneck(cpu,gpu);checks.push(neck==="gpu"?{status:"warn",label:"Balance",detail:"CPU may bottleneck GPU."}:neck==="cpu"?{status:"warn",label:"Balance",detail:"GPU weaker than CPU."}:{status:"ok",label:"Balance",detail:"CPU and GPU well matched."});}else{checks.push({status:"pending",label:"Balance",detail:"Select CPU and GPU."});}
  return checks;
}

// ─── UPDATE PANELS ────────────────────────────────────────────
function updateBuildPanels(){
  const summaryEl=document.getElementById("build-summary"),specListEl=document.getElementById("build-spec-list"),compatListEl=document.getElementById("build-compat-list");
  const powerEl=document.getElementById("stat-power"),socketEl=document.getElementById("stat-socket"),memoryEl=document.getElementById("stat-memory");
  const selectedCount=CARD_IDS.filter(id=>selections[id]).length;
  if(summaryEl)summaryEl.textContent=`${selectedCount}/${CARD_IDS.length} parts selected`;
  if(specListEl){specListEl.innerHTML=CARD_IDS.map(cardId=>{const opt=getSelectedOption(cardId);const label=CATALOG[cardId]?.label||"Component";if(!opt)return`<li class="build-spec-item empty"><span class="spec-label">${label}</span><span class="spec-value">Not selected</span></li>`;return`<li class="build-spec-item"><span class="spec-label">${label}</span><span class="spec-value">${opt.name} <span class="spec-price">${formatPrice(opt.price)}</span></span><span class="spec-meta">${opt.specs.join(" | ")}</span></li>`;}).join("");}
  const cpu=getSelectedOption("card-cpu"),gpu=getSelectedOption("card-gpu"),ram=getSelectedOption("card-ram"),mobo=getSelectedOption("card-mobo");
  const power=estimateWattage(cpu,gpu),cpuM=getOptionMeta(cpu),moboM=getOptionMeta(mobo),ramM=getOptionMeta(ram);
  if(powerEl)powerEl.textContent=power?`${power}W`:"--";
  if(socketEl)socketEl.textContent=cpuM.socket||moboM.socket||"--";
  if(memoryEl)memoryEl.textContent=(ramM.memoryType&&ramM.speed)?`${ramM.memoryType} ${ramM.speed}MHz`:(moboM.memoryType||"--");
  updatePerformancePanel(cpu,gpu,ram);
  updateAnalysisPanel(cpu,gpu);
  if(compatListEl)compatListEl.innerHTML=buildCompatibilityChecks().map(c=>`<li class="build-compat-item ${c.status}"><span class="compat-label">${c.label}</span><span class="compat-detail">${c.detail}</span></li>`).join("");
}

function updatePerformancePanel(cpu,gpu,ram){
  const cpuM=getOptionMeta(cpu),gpuM=getOptionMeta(gpu),ramM=getOptionMeta(ram);
  const storage=getSelectedOption("card-ssd"),cooler=getSelectedOption("card-cooler"),storageM=getOptionMeta(storage);
  const tier=getGamingTier(gpu);
  const el=(id)=>document.getElementById(id);
  if(el("perf-mode-main"))el("perf-mode-main").textContent=tier.main;
  if(el("perf-mode-sub"))el("perf-mode-sub").textContent=tier.sub;
  if(el("perf-cpu-main"))el("perf-cpu-main").textContent=cpu?cpu.name.split(" ").slice(-2).join(" "):"CPU";
  if(el("perf-cpu-sub"))el("perf-cpu-sub").innerHTML=cpu&&cpuM.cores?`${cpuM.cores} Cores<br/>${cpuM.threads} Threads`:"Not selected";
  if(el("perf-gpu-main"))el("perf-gpu-main").textContent=gpu?gpu.name.split(" ").slice(-2).join(" "):"GPU";
  if(el("perf-gpu-sub"))el("perf-gpu-sub").innerHTML=gpu?`${gpuM.tdp||"?"}W TDP<br/>${gpu.specs[0]||""}`:"Not selected";
  if(el("perf-mem-main"))el("perf-mem-main").textContent=ram?`${ramM.capacity||"?"}GB ${ramM.memoryType||""}`:"MEMORY";
  if(el("perf-mem-sub"))el("perf-mem-sub").innerHTML=ram?`${ramM.speed||"?"}MHz<br/>${ram.specs[1]||""}`:"Not selected";
  if(el("perf-storage-main"))el("perf-storage-main").textContent=storage?(storage.name.match(/\d+\s?(GB|TB)/i)||["STORAGE"])[0]:"STORAGE";
  if(el("perf-storage-sub"))el("perf-storage-sub").innerHTML=storage?`${storageM.interface||"?"}<br/>${storage.specs[0]||""}`:"Not selected";
  const watt=estimateWattage(cpu,gpu);
  if(el("perf-power-main"))el("perf-power-main").textContent=watt?`${watt}W`:"POWER DRAW";
  if(el("perf-power-sub"))el("perf-power-sub").innerHTML=watt?"Estimated load<br/>with headroom":"Add a CPU + GPU";
  if(el("perf-badge-res-val")){if(!gpu)el("perf-badge-res-val").textContent="—";else{const t=GPU_TIER[gpu.id]||0;el("perf-badge-res-val").textContent=t>=4?"1440p":"1080p";}}
  if(el("perf-badge-usecase-val")){if(!cpu&&!gpu)el("perf-badge-usecase-val").textContent="—";else{const ct=cpu?CPU_TIER[cpu.id]||0:0,gt=gpu?GPU_TIER[gpu.id]||0:0;el("perf-badge-usecase-val").textContent=(gt>=4&&ct>=4)?"Gaming + Creation":gt>=3?"Gaming":ct>=3?"Productivity":"Everyday Use";}}
  if(el("perf-badge-cooling-val")){if(!cooler)el("perf-badge-cooling-val").textContent="—";else{const n=cooler.name.toLowerCase();el("perf-badge-cooling-val").textContent=n.includes("stealth")||n.includes("stock")?"Stock":n.includes("dark")||n.includes("noctua")||n.includes("nh-d15")?"Silent":"Air Tower";}}
}

const SEVERITY_COPY={excellent:{label:"Excellent Pairing",verdict:p=>`Near-perfect match — only ${p}% difference.`},balanced:{label:"Well Balanced",verdict:p=>`Healthy balance at ${p}%.`},minor:{label:"Minor GPU Bottleneck",verdict:p=>`Mild ${p}% gap. Normal for gaming.`},moderate:{label:"Moderate Bottleneck",verdict:(p,w)=>w==="cpu"?`CPU is ${p}% behind GPU.`:`GPU is ${p}% slower than CPU.`},severe:{label:"Severe Bottleneck",verdict:(p,w)=>w==="cpu"?`Heavy CPU bottleneck at ${p}%.`:`GPU far weaker than CPU (${p}% gap).`}};

function updateAnalysisPanel(cpu,gpu){
  const el=(id)=>document.getElementById(id);
  const bn=calculateBottleneck(cpu,gpu);
  if(!bn){if(el("bn-ring"))el("bn-ring").className="bn-score-ring";if(el("bn-ring"))el("bn-ring").style.setProperty("--ring-pct",0);if(el("bn-pct"))el("bn-pct").textContent="--";if(el("bn-severity"))el("bn-severity").textContent=cpu||gpu?"Pick both CPU and GPU":"Awaiting CPU + GPU";if(el("bn-limited"))el("bn-limited").textContent="";if(el("bn-cpu-fill"))el("bn-cpu-fill").style.width="0%";if(el("bn-gpu-fill"))el("bn-gpu-fill").style.width="0%";if(el("bn-cpu-val"))el("bn-cpu-val").textContent="--";if(el("bn-gpu-val"))el("bn-gpu-val").textContent="--";if(el("bn-verdict")){el("bn-verdict").className="bottleneck-verdict";el("bn-verdict").textContent="Pick a CPU and GPU to analyze.";}
  }else{if(el("bn-ring"))el("bn-ring").className="bn-score-ring "+bn.severity;if(el("bn-ring"))el("bn-ring").style.setProperty("--ring-pct",bn.pct);if(el("bn-pct"))el("bn-pct").textContent=bn.pct;if(el("bn-severity"))el("bn-severity").textContent=SEVERITY_COPY[bn.severity].label;if(el("bn-limited"))el("bn-limited").textContent=bn.limitedBy==="cpu"?"CPU-limited":bn.limitedBy==="gpu"?"GPU-limited (normal)":"";if(el("bn-cpu-fill"))el("bn-cpu-fill").style.width=bn.cpuStrength+"%";if(el("bn-gpu-fill"))el("bn-gpu-fill").style.width=bn.gpuStrength+"%";if(el("bn-cpu-val"))el("bn-cpu-val").textContent=bn.cpuStrength+"%";if(el("bn-gpu-val"))el("bn-gpu-val").textContent=bn.gpuStrength+"%";if(el("bn-verdict")){const tone=bn.severity==="excellent"||bn.severity==="balanced"||bn.severity==="minor"?"ok":bn.severity==="moderate"?"warn":"error";el("bn-verdict").className="bottleneck-verdict "+tone;el("bn-verdict").textContent=SEVERITY_COPY[bn.severity].verdict(bn.pct,bn.limitedBy);}}
  const fpsList=el("fps-list");if(!fpsList)return;
  if(!gpu){fpsList.innerHTML='<li class="fps-row empty"><span class="fps-game">Add a GPU to see FPS estimates.</span></li>';return;}
  fpsList.innerHTML=FPS_GAMES.map(game=>{const fps=estimateFps(cpu,gpu,game);if(fps==null)return"";const cls=fps>=144?"gold":fps>=90?"":fps>=60?"warn":"low";return`<li class="fps-row"><span class="fps-game">${game.name}</span><span class="fps-settings">${game.settings}</span><span class="fps-value ${cls}">${fps} FPS</span></li>`;}).join("");
}

// ─── COMPONENT SELECTOR MODAL ─────────────────────────────────
let activeCardId=null,currentSort="default",currentSearch="";
const compOverlay=document.getElementById("comp-overlay"),compCloseBtn=document.getElementById("comp-close-btn"),compSearchIn=document.getElementById("comp-search-input"),compGrid=document.getElementById("comp-options-grid");

function openComponentModal(card){
  const cardId=card.id;const cat=CATALOG[cardId];if(!cat)return;
  activeCardId=cardId;currentSort="default";currentSearch="";
  document.getElementById("comp-cat-label").textContent=cat.label;
  document.getElementById("comp-panel-title").textContent=cat.title;
  document.getElementById("comp-cat-badge").innerHTML=cat.icon;
  if(compSearchIn)compSearchIn.value="";
  document.querySelectorAll(".sort-btn").forEach(b=>b.classList.remove("active"));
  document.getElementById("sort-default")?.classList.add("active");
  renderOptions();compOverlay.classList.add("open");document.body.style.overflow="hidden";
}
function closeComponentModal(){compOverlay.classList.remove("open");document.body.style.overflow="";activeCardId=null;}

function renderOptions(){
  if(!activeCardId)return;const cat=CATALOG[activeCardId];if(!cat)return;let opts=[...cat.options];
  if(currentSearch){const q=currentSearch.toLowerCase();opts=opts.filter(o=>o.name.toLowerCase().includes(q)||o.specs.some(s=>s.toLowerCase().includes(q)));}
  if(currentSort==="asc")opts.sort((a,b)=>a.price-b.price);
  if(currentSort==="desc")opts.sort((a,b)=>b.price-a.price);
  const selected=selections[activeCardId];
  let recommendedId=null;
  const okOpts=opts.filter(o=>getOptionCompatibility(activeCardId,o).status==="ok").sort((a,b)=>a.price-b.price);
  if(okOpts.length)recommendedId=okOpts[0].id;
  compGrid.innerHTML=opts.length?opts.map(opt=>{
    const compat=getOptionCompatibility(activeCardId,opt);
    const statusLabel=compat.status==="ok"?"Compatible":compat.status==="warn"?"Check Fit":"Incompatible";
    const reason=compat.reasons.length?`<p class="comp-option-reason">${compat.reasons.join(" ")}</p>`:"";
    const isRec=opt.id===recommendedId&&opt.id!==selected;
    return`<div class="comp-option-card ${opt.id===selected?"selected":""} ${compat.status==="error"?"incompatible":""} ${compat.status==="warn"?"warning":""} ${isRec?"recommended":""}" data-option-id="${opt.id}" data-compat="${compat.status}" role="button" tabindex="${compat.status==="error"?"-1":"0"}">
      ${isRec?'<span class="comp-option-rec-badge">★ Recommended</span>':""}
      ${opt.image?`<img src="${opt.image}" alt="${opt.name}" class="comp-option-img"/>`:'<div class="comp-option-img" style="background:rgba(108,71,255,0.1);border-radius:8px;"></div>'}
      <div class="comp-option-info"><p class="comp-option-name">${opt.name}</p><ul class="comp-option-specs">${opt.specs.map(s=>`<li>${s}</li>`).join("")}</ul><span class="comp-option-status ${compat.status}">${statusLabel}</span>${reason}</div>
      <span class="comp-option-price ${opt.price===0?"free":""}">${formatPrice(opt.price)}</span>
    </div>`;}).join(""):'<p class="comp-no-results">No components found.</p>';
  compGrid.querySelectorAll(".comp-option-card").forEach(el=>{
    el.addEventListener("click",()=>{if(el.dataset.compat==="error")return;selectOption(el.dataset.optionId);});
    el.addEventListener("keydown",e=>{if((e.key==="Enter"||e.key===" ")&&el.dataset.compat!=="error")selectOption(el.dataset.optionId);});
  });
}

function selectOption(optionId){
  if(!activeCardId)return;selections[activeCardId]=optionId;
  renderCard(activeCardId);updateTotalPrice();updateBuildPanels();renderOptions();
  setTimeout(closeComponentModal,320);
}

function getTotalPrice(){let t=0;Object.entries(selections).forEach(([cardId,optId])=>{const opt=CATALOG[cardId]?.options.find(o=>o.id===optId);if(opt)t+=opt.price;});return t;}
function updateTotalPrice(){const el=document.querySelector(".summary-price");if(el)animatePrice(el,getTotalPrice());}

// Sort & search handlers
document.querySelectorAll(".sort-btn").forEach(btn=>{btn.addEventListener("click",()=>{document.querySelectorAll(".sort-btn").forEach(b=>b.classList.remove("active"));btn.classList.add("active");currentSort=btn.dataset.sort;renderOptions();});});
compSearchIn?.addEventListener("input",e=>{currentSearch=e.target.value;renderOptions();});
compCloseBtn?.addEventListener("click",closeComponentModal);
compOverlay?.addEventListener("click",e=>{if(e.target===compOverlay)closeComponentModal();});
document.addEventListener("keydown",e=>{if(e.key==="Escape"&&activeCardId)closeComponentModal();});

// ─── ADD BUTTONS & INIT ───────────────────────────────────────
function setupAddButtons(){document.querySelectorAll(".card-add-btn").forEach(btn=>{btn.addEventListener("click",e=>{const card=e.currentTarget.closest(".component-card");if(card)openComponentModal(card);});});}
window.addEventListener("load",setupAddButtons);
window.addEventListener("load",()=>{renderAllCards();updateTotalPrice();updateBuildPanels();});

// ─── RESET BUILD ──────────────────────────────────────────────
function resetBuild(){CARD_IDS.forEach(id=>{selections[id]=null;});renderAllCards();updateTotalPrice();updateBuildPanels();showToast("Build reset.");}
document.getElementById("btn-reset-build")?.addEventListener("click",()=>{if(!CARD_IDS.some(id=>selections[id])){showToast("Nothing to reset.");return;}resetBuild();});

// ─── SUBMIT TO CASHIER ────────────────────────────────────────
const overlay=document.getElementById("modal-overlay");
const btnCTA=document.getElementById("btn-build-cta");
const btnHdrBld=document.getElementById("btn-header-build");
const btnClose=document.getElementById("modal-close");
const btnCont=document.getElementById("btn-continue");
const btnChkout=document.getElementById("btn-checkout");

function openModal(){
  const total=getTotalPrice();
  const modalTotal=document.getElementById("modal-total");
  if(modalTotal)modalTotal.textContent=formatPrice(total);
  overlay.classList.add("open");document.body.style.overflow="hidden";
}
function closeModal(){overlay.classList.remove("open");document.body.style.overflow="";}

btnCTA?.addEventListener("click",openModal);
btnHdrBld?.addEventListener("click",openModal);
btnClose?.addEventListener("click",closeModal);
btnCont?.addEventListener("click",closeModal);
overlay?.addEventListener("click",e=>{if(e.target===overlay)closeModal();});

btnChkout?.addEventListener("click",async()=>{
  closeModal();
  // Build the items payload: { slug: product_id }
  const items={};
  for(const [cardId,optId] of Object.entries(selections)){
    if(!optId)continue;
    const slug=CARD_TO_SLUG[cardId];
    if(slug)items[slug]=parseInt(optId);
  }
  if(Object.keys(items).length===0){showToast("Select at least one component.");return;}
  showToast("Submitting build...");
  try{
    const url=(typeof APP_CONFIG!=='undefined')?APP_CONFIG.apiSubmit:'/pcstore/api/submit_build.php';
    const res=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({items})});
    const data=await res.json();
    if(data.success){
      document.getElementById("pickup-code-display").textContent=data.pickup_code;
      document.getElementById("success-body").innerHTML=`Your pickup code is: <strong style="font-size:1.8rem;letter-spacing:4px;color:#6C47FF;">${data.pickup_code}</strong><br><small>Present this at the counter within 48 hours.<br>Total: ${formatPrice(data.total_price)}</small>`;
      document.getElementById("success-overlay").classList.add("open");
      document.body.style.overflow="hidden";
    }else{
      const msg=data.errors?data.errors.join(' '):data.error||'Submission failed.';
      showToast('❌ '+msg);
    }
  }catch(err){console.error(err);showToast("Network error. Please try again.");}
});

// New build button in success modal
document.getElementById("btn-new-build")?.addEventListener("click",()=>{
  document.getElementById("success-overlay").classList.remove("open");
  document.body.style.overflow="";
  resetBuild();
});

// ─── SAVE BUILD (local) ───────────────────────────────────────
const btnSave=document.getElementById("btn-save");
btnSave?.addEventListener("click",()=>{btnSave.textContent="♥ Build Saved!";btnSave.style.color="#6C47FF";btnSave.style.borderColor="#6C47FF";showToast("♡ Build saved locally!");setTimeout(()=>{btnSave.textContent="♡ Save Build";btnSave.style.color="";btnSave.style.borderColor="";},2500);});

// ─── LOAD CATALOG ON PAGE LOAD ────────────────────────────────
window.addEventListener("load",loadCatalogFromAPI);
