document.addEventListener('DOMContentLoaded', function() {
  const messagesContainer = document.getElementById('messages');
  if (!messagesContainer) return;

  const chatInput = document.getElementById('chat-input');
  const sendBtn = document.getElementById('send-btn');
  const productGrid = document.getElementById('product-grid');
  const sidePanel = document.getElementById('side-panel');
  const sideContent = document.getElementById('side-panel-content');
  const orderBtnTop = document.getElementById('order-drawer-btn-top');

  const ROOT=getComputedStyle(document.documentElement); const PRIMARY_COLOR=(ROOT.getPropertyValue('--ac-accent')||'').trim()||'rgba(255,255,255,0.14)';
  const ROOT2=getComputedStyle(document.documentElement); const USER_BUBBLE=(ROOT2.getPropertyValue('--ac-bubble-user')||'').trim()||'rgba(255,255,255,0.16)';
  const WELCOME_MESSAGE = (window.aura_chatbot_data && aura_chatbot_data.welcome_message) || "Hello! Let's find a product.";
  const cannedResponsesRaw = (window.aura_chatbot_data && aura_chatbot_data.canned_responses_raw) || "";
  const printPlacements = ((window.aura_chatbot_data && aura_chatbot_data.placements_raw) || "Front Center\nBack Center").split('\n').filter(Boolean);

  if (orderBtnTop) {
    orderBtnTop.style.background = USER_BUBBLE;
    orderBtnTop.textContent = "View Order (0)";
  }

  function expandSynonymsLocally(q){
    const s = (q || "").trim().toLowerCase();
    const out = new Set([s]);
    const add = (...arr)=>arr.forEach(t=>out.add(t));
    if (/t\s*-?\s*shirt|^tshirt$|^t shirt$|^tee$/.test(s)){ add('t-shirt','tshirt','t shirt','tee'); }
    if (/hood/.test(s) || /hooded\s*sweatshirt/.test(s)){ add('hoodie','hoody','hooded sweatshirt'); }
    if (/sweatshirt|crewneck|jumper/.test(s)){ add('sweatshirt','crewneck','jumper'); }
    if (/hat|cap|beanie/.test(s)){ add('hat','cap','beanie'); }
    return Array.from(out);
  }

  let cannedResponses = cannedResponsesRaw.split('\n').reduce((acc, line) => {
    const parts = line.split('|');
    if (parts.length === 2) acc[parts[0].trim().toLowerCase()] = parts[1].trim();
    return acc;
  }, {});

  let orderSummary = [];

  sendBtn.addEventListener('click', handleUserInput);
  chatInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') handleUserInput(); });
  messagesContainer.addEventListener('click', handleOptionButtonClick);
  productGrid.addEventListener('click', (e) => {
    const card = e.target.closest('.product-card');
    if (card) openProductPanel(card.dataset.id);
  });
  if (orderBtnTop) {
      orderBtnTop.addEventListener('click', openOrderPanel);
  }
  
  sidePanel.addEventListener('click', (e)=>{
    if (e.target && e.target.id === 'panel-close-btn') closeSidePanel();
    
    if (e.target && e.target.id === 'panel-customize-btn'){
      const parsedColors = JSON.parse(decodeURIComponent(e.target.dataset.colors || '[]'));
      const parsedSizes  = JSON.parse(decodeURIComponent(e.target.dataset.sizes  || '[]'));
      const norm = (arr)=>{
        const out = [];
        (Array.isArray(arr)?arr:[]).forEach(v=>{
          if (typeof v === 'string'){
            v.split(/[ ,|\/]+/).forEach(p=>{ const t=p.trim(); if(t) out.push(t); });
          }
        });
        return Array.from(new Set(out));
      };
      const p = {
        id: e.target.dataset.id,
        name: e.target.dataset.name,
        image_url: e.target.dataset.image,
        price_html: e.target.dataset.price || '',
        colors: norm(parsedColors),
        sizes: norm(parsedSizes)
      };
      openWizardPanel(p);
    }

    if (e.target && e.target.classList && e.target.classList.contains('ac-color-chip')){
      const chip = e.target;
      const val = chip.getAttribute('data-value') || chip.textContent.trim();
      chip.classList.toggle('selected');
      if (!window.wizard) return;
      if (!Array.isArray(window.wizard.selected.colors)) window.wizard.selected.colors = [];
      if (chip.classList.contains('selected')){
        if (!window.wizard.selected.colors.includes(val)) window.wizard.selected.colors.push(val);
      } else {
        window.wizard.selected.colors = window.wizard.selected.colors.filter(v=>v!==val);
      }
    }
    
    if (e.target && e.target.id === 'wizard-next') wizardNext();
    if (e.target && e.target.id === 'wizard-back') wizardBack();
    if (e.target && e.target.id === 'wizard-add-item') wizardAddItem();
    if (e.target && e.target.id === 'order-send') sendOrderEmail();
    if (e.target && e.target.classList.contains('badge-remove')){
      const idx = parseInt(e.target.dataset.idx,10);
      if (!isNaN(idx)) { orderSummary.splice(idx,1); refreshOrderPanel(); updateOrderCount(); }
    }
    if (e.target && e.target.classList.contains('placement-chip')){
      const chip = e.target;
      const val = chip.getAttribute('data-val') || chip.textContent.trim();
      chip.classList.toggle('selected');
      chip.classList.toggle('bg-white/20');
      if (!window.wizard) return;
      if (!Array.isArray(window.wizard.selected.placements)) window.wizard.selected.placements = [];
      if (chip.classList.contains('selected')){
        if (!window.wizard.selected.placements.includes(val)) window.wizard.selected.placements.push(val);
      } else {
        window.wizard.selected.placements = window.wizard.selected.placements.filter(v=>v!==val);
      }
    }
  });

  function init(){
    addBotMessage(WELCOME_MESSAGE + '\n\nEnter a style number or search term to start.');
  }

  function handleUserInput(){
    const messageText = chatInput.value.trim();
    if (!messageText) return;
    addUserMessage(messageText);
    chatInput.value='';
    const cannedResponse = cannedResponses[messageText.toLowerCase()];
    if (cannedResponse) return setTimeout(()=>addBotMessage(cannedResponse), 200);
    getGeminiProductSearchResponse(messageText);
  }

  function handleOptionButtonClick(e){
    if (!e.target.classList.contains('option-button')) return;
    const value = e.target.dataset.value;
    addUserMessage(e.target.innerText);
    e.target.parentElement.querySelectorAll('.option-button').forEach(btn=>btn.disabled=true);
    if (value === 'mode_search'){
      addBotMessage("Great! What kind of product are you looking for?");
    }
  }

  function openSidePanel(){ sidePanel.classList.remove('hidden'); }
  function closeSidePanel(){ sidePanel.classList.add('hidden'); sideContent.innerHTML=''; }

  async function openProductPanel(productId){
    openSidePanel();
    sideContent.innerHTML = `<div class="p-6"><div class="text-center text-gray-300">Loading...</div></div>`;
    const fd = new FormData();
    fd.append('action', 'aura_fetch_product_details');
    fd.append('product_id', productId);
    try {
      const res = await fetch(aura_chatbot_data.ajax_url, { method:'POST', body: fd });
      const json = await res.json();
      if (json.success){
        const colors = JSON.stringify(json.data.colors || []);
        const sizes  = JSON.stringify(json.data.sizes || []);
        sideContent.innerHTML = `
          <div class="p-4 border-b border-white/10 flex items-center justify-between">
            <div class="text-white font-bold">Product Details</div>
            <button id="panel-close-btn" class="text-gray-400 hover:text-white text-2xl leading-none">&times;</button>
          </div>
          <div class="p-6 space-y-4">
            <div class="apc-img-wrap detail"><div class="apc-img-wrap detail"><img src="${json.data.image_url}" alt="${json.data.name}" class="apc-img"></div></div>
            <h2 class="text-xl font-bold text-white">${json.data.name}</h2>
            <div class="text-lg text-white">${json.data.price_html}</div>
            <div class="text-sm text-gray-400 prose">${json.data.description}</div>
            <button id="panel-customize-btn"
              data-id="${json.data.id}" data-name="${json.data.name}" data-image="${json.data.image_url}"
              data-price="${json.data.price_html}"
              data-colors="${encodeURIComponent(colors)}"
              data-sizes="${encodeURIComponent(sizes)}"
              class="mt-4 w-full text-white font-bold py-3 px-4 rounded-lg" style="background:${PRIMARY_COLOR};">
              Customize This Product
            </button>
          </div>`;
      } else {
        sideContent.innerHTML = `<div class="p-6 text-center text-red-400">Could not load product details.</div>`;
      }
    } catch(e){
      sideContent.innerHTML = `<div class="p-6 text-center text-red-400">An error occurred.</div>`;
    }
  }

  function openOrderPanel(){ openSidePanel(); renderOrderPanel(); }
  function renderOrderPanel(){
    sideContent.innerHTML = `
      <div class="p-4 border-b border-white/10 flex items-center justify-between">
        <div class="text-white font-bold">Order</div>
        <button id="panel-close-btn" class="text-gray-400 hover:text-white text-2xl leading-none">&times;</button>
      </div>
      <div class="p-6">
        ${orderSummary.length === 0 ? `<p class="text-gray-400">No items yet. Add items via "Customize" on a product.</p>` : ''}
        <div class="space-y-4">${orderSummary.map((it, i)=>`
          <div class="p-4 rounded-lg" style="background:var(--ac-surface);">
            <div class="flex items-start justify-between gap-3">
              <div>
                <h3 class="text-white font-semibold">${it.product || '(Unnamed product)'}</h3>
                <div class="text-sm text-gray-300 mt-1">
                  <div><strong>Colors:</strong> ${it.colors || '-'}</div>
                  <div><strong>Size & Quantities:</strong> ${
                    it.matrix
                      ? Object.keys(it.matrix).map(c=>{
                          const inner = Object.entries(it.matrix[c]).filter(([,q])=>q>0).map(([sz,q])=>`${sz}:${q}`).join(', ');
                          return inner ? `${c}: ${inner}` : '';
                        }).filter(Boolean).join('; ')
                      : (it.quantity || '-')
                  }</div>
                  <div><strong>Artwork:</strong> ${it.artwork || '-'}</div>
                  ${it.artwork_file ? `<div><strong>Artwork file:</strong> <a href='${it.artwork_file_url||'#'}' target='_blank' rel='noopener'>${it.artwork_file}</a></div>` : ''}
                  <div><strong>Placements:</strong> ${Array.isArray(it.placements)&&it.placements.length ? it.placements.join(', ') : (it.placement||'-')}</div>
                </div>
              </div>
              <button class="badge badge-remove" data-idx="${i}">Remove &times;</button>
            </div>
          </div>`).join('')}
        </div>
        <div class="mt-6 ${orderSummary.length? '' : 'hidden'}">
          <button id="order-send" class="w-full text-white font-bold py-3 px-4 rounded-lg" style="background:${PRIMARY_COLOR};">Complete and Send Order</button>
        </div>
      </div>`;
  }
  function refreshOrderPanel(){ renderOrderPanel(); }
  function updateOrderCount(){ 
    if(orderBtnTop) {
      orderBtnTop.textContent = `View Order (${orderSummary.length})`;
    }
  }

  // Make wizard global
  window.wizard = {
    step: 0,
    data: { product:'', colors:'', sizes:'', quantity:'', artwork:'', placement:'' },
    fromProduct: null,
    available: { colors: [], sizes: [] },
    selected: { colors: [] },
    matrix: {}
  };

  function openWizardPanel(product){
    openSidePanel();
    wizard.step = 0;
    wizard.fromProduct = product && (product.id || product.name) ? product : null;
    const norm = (arr)=>{
      const out = [];
      (Array.isArray(arr)?arr:[]).forEach(v=>{
        if (typeof v === 'string'){
          v.split(/[ ,|\/]+/).forEach(p=>{ const t=p.trim(); if(t) out.push(t); });
        }
      });
      return Array.from(new Set(out));
    };
    wizard.available.colors = norm(product && product.colors);
    wizard.available.sizes  = norm(product && product.sizes);
    wizard.data = { product: (product && product.name) || '', colors:'', sizes:'', quantity:'', artwork:'', placements:[] };
    wizard.selected.colors = [];
    wizard.matrix = {};
    renderWizardPanel();
  }

  function renderWizardPanel(){
    const p = wizard.fromProduct;
    sideContent.innerHTML = `
      <div class="p-4 border-b border-white/10 flex items-center justify-between">
        <div class="text-white font-bold">Customize ${wizard.data.product || (p && p.name) || 'Product'}</div>
        <button id="panel-close-btn" class="text-gray-400 hover:text-white text-2xl leading-none">&times;</button>
      </div>
      <div class="p-6 space-y-5">
        ${p && p.image_url ? `<div class="apc-img-wrap detail"><img src="${p.image_url}" class="apc-img"></div>` : ''}
        ${p && p.price_html ? `<div class="text-white/90">${p.price_html}</div>` : ''}
        ${stepContent()}
      </div>`;
  }

  function stepContent(){
    switch(wizard.step){
      case 0: return stepProduct();
      case 1: return stepColors();
      case 2: return stepSizesAndQty();
      case 3: return stepArtwork();
      case 4: return stepPlacement();
      default: return stepReview();
    }
  }

  function inputRow(label, html){ return `<label class="block text-sm text-gray-300 mb-1">${label}</label>${html}`; }

  function stepProduct(){
    const p = wizard.fromProduct;
    return `
      ${inputRow('Product name', `<input id="wz-product" value="${(wizard.data.product || (p && p.name) || '')}" class="w-full bg-transparent border border-white/10 rounded p-2 text-white">`)}
      <div class="flex justify-end gap-2 mt-4">
        <button id="wizard-next" class="px-4 py-2 rounded text-white" style="background:${PRIMARY_COLOR};">Next</button>
      </div>`;
  }

  

function stepColors(){
  if (wizard.available.colors.length){
    // Pre-select chips based on wizard.selected.colors
    const selectedSet = new Set(wizard.selected.colors);
    const chips = wizard.available.colors
      .map(c=>`<div class="ac-color-chip ${selectedSet.has(c) ? 'selected' : ''}" data-value="${c}">${c}</div>`).join('');
    return `
      ${inputRow('Colors (choose one or more)', `<div id="wz-color-chips" class="flex flex-wrap gap-2">${chips}</div>`)}
      <p class="text-xs text-gray-400 mt-2">Tap to select. You can select multiple.</p>
      <div class="flex justify-between mt-4">
        <button id="wizard-back" class="px-4 py-2 rounded bg-white/10 text-white">Back</button>
        <button id="wizard-next" class="px-4 py-2 rounded text-white" style="background:${PRIMARY_COLOR};">Next</button>
      </div>`;
  } else {
    return `
      ${inputRow('Colors (comma separated)', `<input id="wz-colors" class="ac-input w-full bg-transparent border border-white/10 rounded p-2 text-white" value="${wizard.data.colors||''}">`)}
      <div class="flex justify-between mt-4">
        <button id="wizard-back" class="px-4 py-2 rounded bg-white/10 text-white">Back</button>
        <button id="wizard-next" class="px-4 py-2 rounded text-white" style="background:${PRIMARY_COLOR};">Next</button>
      </div>`;
  }
}
function stepSizesAndQty(){
    const colors = wizard.selected.colors.length ? wizard.selected.colors : [(wizard.data.colors || '').split(',')[0].trim() || ''];

    if (wizard.available.sizes.length && colors.length > 1){
      const sizes = wizard.available.sizes;
      const thead = `<thead><tr>
        <th style="width:120px;text-align:left;color:var(--ac-text-muted);font-weight:500;"></th>
        ${sizes.map(sz=>`<th style="text-align:center;color:var(--ac-text-muted);font-weight:500;">${sz}</th>`).join('')}
      </tr></thead>`;

      const rows = colors.map(color => `<tr>
        <td style="color:var(--ac-text);font-weight:600;">${color}</td>
        ${sizes.map(sz => `
          <td style="text-align:center;">
            <input type="text" inputmode="numeric" pattern="[0-9]*" min="0" step="1"
              value="${wizard.matrix[color]?.[sz] ?? 0}"
              class="qty-cell qty-matrix"
              data-color="${color}" data-size="${sz}"
              style="width:64px;background:transparent;border:1px solid rgba(255,255,255,0.1);border-radius:6px;padding:6px 8px;color:var(--ac-text);text-align:center;">
          </td>`).join('')}
      </tr>`).join('');

      return `
        ${inputRow('Quantities by color & size', `
          <div class="overflow-x-auto">
            <table class="ac-matrix" style="width:100%;border-collapse:separate;border-spacing:8px 8px;">
              ${thead}
              <tbody>${rows}</tbody>
            </table>
          </div>`)}
        <p class="text-xs text-gray-400 mt-2">Enter quantities for each color/size cell.</p>
        <div class="flex justify-between mt-4">
          <button id="wizard-back" class="px-4 py-2 rounded bg-white/10 text-white">Back</button>
          <button id="wizard-next" class="px-4 py-2 rounded text-white" style="background:${PRIMARY_COLOR};">Next</button>
        </div>`;
    }

    if (wizard.available.sizes.length){
      // Handle single color case
      const singleColor = colors[0] || '';
      const rows = wizard.available.sizes.map(sz=>`
        <div class="flex items-center justify-between gap-3 p-2 rounded border border-white/10">
          <div class="text-white font-medium w-16">${sz}</div>
          <input type="text" inputmode="numeric" pattern="[0-9]*" min="0" step="1"
                 value="${wizard.matrix[singleColor]?.[sz] ?? 0}"
                 class="qty-cell w-24 bg-transparent border border-white/10 rounded p-2 text-white qty-input"
                 data-size="${sz}">
        </div>`).join('');
      return `
        ${inputRow(`Quantities by size${singleColor ? ' for ' + singleColor : ''}`, `<div class="space-y-2">${rows}</div>`)}
        <p class="text-xs text-gray-400 mt-2">Set a quantity for each size.</p>
        <div class="flex justify-between mt-4">
          <button id="wizard-back" class="px-4 py-2 rounded bg-white/10 text-white">Back</button>
          <button id="wizard-next" class="px-4 py-2 rounded text-white" style="background:${PRIMARY_COLOR};">Next</button>
        </div>`;
    } else {
      return `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          ${inputRow('Sizes (comma separated)', `<input id="wz-sizes" placeholder="e.g., XS, S, M, L, XL" class="w-full bg-transparent border border-white/10 rounded p-2 text-white" value="${wizard.data.sizes}">`)}
          ${inputRow('Quantities by size', `<input id="wz-qty" placeholder="e.g., 10S, 15M or S:10, M:15" class="w-full bg-transparent border border-white/10 rounded p-2 text-white" value="${wizard.data.quantity}">`)}
        </div>
        <p class="text-xs text-gray-400 mt-2">Tip: "10S, 15M", "S:10, M:15", or "S=10 M=15".</p>
        <div class="flex justify-between mt-4">
          <button id="wizard-back" class="px-4 py-2 rounded bg-white/10 text-white">Back</button>
          <button id="wizard-next" class="px-4 py-2 rounded text-white" style="background:${PRIMARY_COLOR};">Next</button>
        </div>`;
    }
  }

  function stepArtwork(){
  // This function now just renders HTML. The logic for the custom select is outside.
  // The logic for the file upload is handled by the global patch scripts.
  return `
    ${inputRow('Artwork', `
      <div class="ac-select-custom" data-select="wz-art">
        <button type="button" class="ac-select-btn" id="wz-art-btn" aria-haspopup="listbox" aria-expanded="false">
          <span class="label">${wizard.data.artwork || 'Select...'}</span>
          <span class="chev"></span>
        </button>
        <div class="ac-options hidden" role="listbox" aria-label="Artwork options" id="wz-art-options">
          <div class="ac-option" role="option" data-value="Email Later">Email Later</div>
          <div class="ac-option" role="option" data-value="Ready to email now">Ready to email now</div>
          <div class="ac-option" role="option" data-value="Need help creating">Need help creating</div>
          <div class="ac-option" role="option" data-value="Upload Artwork">Upload Artwork</div>
        </div>
      </div>
      <select id="wz-art" name="wz-art" class="hidden" style="display:none">
        <option value="" ${!wizard.data.artwork?'selected':''}>Select...</option>
        <option value="Email Later" ${wizard.data.artwork==='Email Later'?'selected':''}>Email Later</option>
        <option value="Ready to email now" ${wizard.data.artwork==='Ready to email now'?'selected':''}>Ready to email now</option>
        <option value="Need help creating" ${wizard.data.artwork==='Need help creating'?'selected':''}>Need help creating</option>
        <option value="Upload Artwork" ${wizard.data.artwork==='Upload Artwork'?'selected':''}>Upload Artwork</option>
      </select>
    `)}
    <div class="mt-3" id="wz-art-upload-wrap" style="display:${wizard.data.artwork === 'Upload Artwork' ? 'block' : 'none'}">
      <label class="text-sm text-gray-400 block mb-1">Upload artwork (max 10MB):</label>
      <label class="ac-file-btn">
        <input id="wz-art-file" type="file" class="sr-only" accept=".png,.jpg,.jpeg,.pdf,.ai,.psd,.eps,.svg,.zip">
        <span>Browse&hellip;</span>
      </label>
      </div>
    <div class="flex justify-between mt-4">
      <button id="wizard-back" class="px-4 py-2 rounded bg-white/10 text-white">Back</button>
      <button id="wizard-next" class="px-4 py-2 rounded text-white" style="background:${PRIMARY_COLOR};">Next</button>
    </div>`;
}

function stepPlacement(){
  const selected = Array.isArray(wizard.selected.placements) ? new Set(wizard.selected.placements) : (Array.isArray(wizard.data.placements) ? new Set(wizard.data.placements) : new Set());
  const chips = printPlacements
    .map(p => `<button class="placement-chip ac-placement-chip px-3 py-1 rounded border border-white/20 ${selected.has(p)?'bg-white/20 selected':''}" data-val="${p}">${p}</button>`)
    .join(' ');
  return `
    ${inputRow('Print placement (choose one or more)', `<div class="flex flex-wrap gap-2" id="wz-placement">${chips}</div>`)}
    <p class="text-xs text-gray-400 mt-2">Tap to select. You can select multiple.</p>
    <div class="flex justify-between mt-4">
      <button id="wizard-back" class="px-4 py-2 rounded bg-white/10 text-white">Back</button>
      <button id="wizard-next" class="px-4 py-2 rounded text-white" style="background:${PRIMARY_COLOR};">Next</button>
    </div>`;
}
function stepReview(){
    const matrixLines = [];
    Object.keys(wizard.matrix || {}).forEach(color => {
      const line = Object.entries(wizard.matrix[color] || {})
        .filter(([,q])=>q>0)
        .map(([sz,q])=>`${sz}:${q}`).join(', ');
      if (line) matrixLines.push(`<div><strong>${color}:</strong> ${line}</div>`);
    });
    
    const qtySummary = matrixLines.length ? matrixLines.join('') : (wizard.data.quantity || '-');
    const artworkSummary = (wizard.data.artwork_filename ? `Uploaded: ${wizard.data.artwork_filename}` : (wizard.data.artwork || '-'));

    return `
      <h3 class="text-white font-semibold mb-3">Review item</h3>
      <div class="text-gray-300 text-sm space-y-1" id="order-summary-lines">
        <div><strong>Product:</strong> ${wizard.data.product || (wizard.fromProduct && wizard.fromProduct.name) || '-'}</div>
        <div><strong>Colors:</strong> ${wizard.data.colors || '-'}</div>
        <div><strong>Size & Quantities:</strong> ${qtySummary}</div>
        <div><strong>Artwork:</strong> ${artworkSummary}</div>
        <div><strong>Placements:</strong> ${Array.isArray(wizard.data.placements)&&wizard.data.placements.length ? wizard.data.placements.join(', ') : '-'}</div>
      </div>
      <div class="flex justify-between mt-6">
        <button id="wizard-back" class="px-4 py-2 rounded bg-white/10 text-white">Back</button>
        <button id="wizard-add-item" class="px-4 py-2 rounded text-white" style="background:${PRIMARY_COLOR};">Add to Order</button>
      </div>`;
  }

  function wizardNext(){
    if (wizard.step === 0){
      wizard.data.product = (document.getElementById('wz-product')?.value || '').trim() || (wizard.fromProduct && wizard.fromProduct.name) || '';
      if (!wizard.data.product){ addBotMessage("Please name the product."); return; }
    }

    if (wizard.step === 1){
      if (wizard.available.colors.length){
        // Data is already set by the sidePanel click listener
        if (wizard.selected.colors.length === 0){ addBotMessage("Pick at least one color."); return; }
        wizard.data.colors = wizard.selected.colors.join(', ');
        wizard.selected.colors.forEach(c => { if (!wizard.matrix[c]) wizard.matrix[c] = {}; });
      } else {
        const free = (document.getElementById('wz-colors')?.value || '').trim();
        if (!free){ addBotMessage("Add at least one color."); return; }
        const sel = free.split(',').map(s=>s.trim()).filter(Boolean);
        wizard.selected.colors = sel;
        wizard.data.colors = sel.join(', ');
        sel.forEach(c => { if (!wizard.matrix[c]) wizard.matrix[c] = {}; });
      }
    }

    if (wizard.step === 2){
      if (wizard.available.sizes.length){
        const matrixInputs = Array.from(document.querySelectorAll('.ac-matrix input[data-color][data-size]'));
        if (matrixInputs.length){
          // Multi-color grid
          const m = {};
          let any = 0;
          matrixInputs.forEach(inp => {
            const color = inp.dataset.color;
            const size  = inp.dataset.size;
            const qty   = Math.max(0, parseInt(inp.value || '0', 10));
            if (!m[color]) m[color] = {};
            m[color][size] = qty;
            if (qty > 0) any++;
          });
          if (!any){ addBotMessage("Add at least one quantity in the grid."); return; }
          wizard.matrix = m;
          // Consolidate sizes/qty for review screen (optional)
          const allSizes = new Set();
          Object.values(m).forEach(sizeMap => Object.keys(sizeMap).forEach(sz => allSizes.add(sz)));
          wizard.data.sizes = Array.from(allSizes).join(', ');
          wizard.data.quantity = '[See grid]'; // Placeholder
        } else {
          // Single color case
          const colors = wizard.selected.colors.length ? wizard.selected.colors : [(wizard.data.colors || '').split(',')[0].trim() || ''];
          const singleColor = colors[0] || '';
          
          const inputs = Array.from(document.querySelectorAll('.qty-input'));
          const map = {};
          let total = 0;
          inputs.forEach(inp => {
            const qty = Math.max(0, parseInt(inp.value || '0', 10));
            const size = inp.dataset.size.toUpperCase();
            if (qty > 0){ map[size] = qty; total += qty; }
          });
          if (total === 0){ addBotMessage("Add at least one size with quantity > 0."); return; }
          wizard.matrix = { [singleColor]: map };
          wizard.data.sizes = Object.keys(map).join(', ');
          wizard.data.quantity = Object.entries(map).map(([k,v])=>`${k}:${v}`).join(', ');
        }
      } else {
        wizard.data.sizes = (document.getElementById('wz-sizes')?.value || '').trim();
        wizard.data.quantity = (document.getElementById('wz-qty')?.value || '').trim();
        if (!wizard.data.sizes || !wizard.data.quantity){
          addBotMessage("Add sizes and quantities."); return;
        }
        // Build a simple single-color matrix from freeform
        const color = (wizard.selected.colors[0] || wizard.data.colors.split(',')[0] || '').trim() || 'Default';
        const parsed = parseQuantities(wizard.data.quantity.toUpperCase());
        if (Object.keys(parsed).length === 0){ addBotMessage("Could not parse quantities. Try S:10, M:15"); return; }
        wizard.matrix = { [color]: parsed };
        if(!wizard.data.colors) wizard.data.colors = color;
      }
    }

    if (wizard.step === 3){
      // Data is already set by global listeners (select change, upload success)
      wizard.data.artwork = document.getElementById('wz-art').value;
      if (!wizard.data.artwork) { addBotMessage("Please select an artwork option."); return; }
    }

    if (wizard.step === 4){
      // Data is already set by sidePanel click listener
      if (!wizard.selected.placements || wizard.selected.placements.length === 0) {
          addBotMessage("Pick at least one placement before continuing."); 
          return; 
      }
      wizard.data.placements = wizard.selected.placements;
    }

    wizard.step = Math.min(5, wizard.step + 1);
    renderWizardPanel();
  }

  function wizardBack(){ wizard.step = Math.max(0, wizard.step - 1); renderWizardPanel(); }

  function wizardAddItem(){
    // Final consolidation of data before adding
    const item = {
      product: wizard.data.product || (wizard.fromProduct && wizard.fromProduct.name) || '',
      colors: wizard.data.colors,
      quantity: wizard.data.quantity,
      matrix: wizard.matrix,
      artwork: wizard.data.artwork || 'Client will email later',
      placements: (Array.isArray(wizard.data.placements)? wizard.data.placements : []),
      artwork_file: (wizard.data.artwork_filename||wizard.data.artwork_file||''),
      artwork_file_url: (wizard.data.artwork_url||wizard.data.artwork_file_url||''),
      artwork_attachment_id: (wizard.data.artwork_attachment_id || 0)
    };
    orderSummary.push(item);
    updateOrderCount();
    renderOrderPanel(); // This closes the wizard and shows the order
  }

  function parseQuantities(text){
    const out = {};
    if (!text) return out;
    const pairs = text.split(/[,;\n]+/);
    for (const p of pairs){
      const t = p.trim();
      if (!t) continue;
      let m = t.match(/^(\d+)\s*([A-Za-z0-9\-+]+)$/); // e.g. 10S
      if (m){ out[m[2].toUpperCase()] = (out[m[2].toUpperCase()]||0) + parseInt(m[1],10); continue; }
      m = t.match(/^([A-Za-z0-9\-+]+)\s*[:=\-]\s*(\d+)$/); // e.g. S:10 or S=10
      if (m){ out[m[1].toUpperCase()] = (out[m[1].toUpperCase()]||0) + parseInt(m[2],10); continue; }
    }
    return out;
  }

  function addUserMessage(message){
    messagesContainer.innerHTML += `<div class="flex justify-end mb-4"><div class="text-white p-3 rounded-lg max-w-xs" style="background-color:${USER_BUBBLE};"><p class="text-sm">${message}</p></div></div>`;
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
  }
  function addBotMessage(message, options = []){
    const optionsHTML = options.length ? `<div class="flex flex-wrap mt-2">${options.map(opt => `<button class="option-button" data-value="${opt.value}">${opt.text}</button>`).join('')}</div>` : '';
    messagesContainer.innerHTML += `
      <div class="flex items-start gap-3 justify-start mb-4">
        <div class="chatbot-avatar w-10 h-10 text-white rounded-full flex items-center justify-center font-bold flex-shrink-0" style="background-color:${PRIMARY_COLOR};">A</div>
        <div class="p-3 rounded-lg max-w-xs" style="background-color:var(--ac-surface-2);"><p class="text-sm text-gray-200">${message}</p>${optionsHTML}</div>
      </div>`;
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
  }
  
  // Make bot message function global for patches
  window.auraAppendAssistantMessage = addBotMessage;

  async function getGeminiProductSearchResponse(userQuery){
    const apiKey = (aura_chatbot_data && aura_chatbot_data.api_key) ? aura_chatbot_data.api_key : "";
    const fallback = () => { const local = expandSynonymsLocally(userQuery); return fetchProducts(local); };
    if (!apiKey) return fallback();

    try {
      const apiUrl = `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-05-20:generateContent?key=${apiKey}`;
      const systemPrompt = `
You are a product-search assistant for a garment store.
Given a user query, produce a compact set of normalized search terms that include common synonyms.

Rules:
- Return a single line starting with: AURA_FETCH_PRODUCTS_JSON::
- Immediately after that, return valid JSON with the shape:
  {"terms":["term1","term2", ...]}
- Include synonyms (case-insensitive) where appropriate. Examples:
  tshirt -> ["t-shirt","tshirt","t shirt","tee"]
  hoodie -> ["hoodie","hoody","hooded sweatshirt"]
  sweatshirt -> ["sweatshirt","crewneck","jumper"]
  hat -> ["hat","cap","beanie"]
- Keep 2–6 terms max. Include the original user wording if it seems useful.
- Do NOT include explanations in the JSON; keep JSON minimal.
- If the prompt is not about product search, do not emit the tag.
`;

      const resp = await fetch(apiUrl, {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ contents:[{ parts:[{ text:userQuery }]}], systemInstruction:{ parts:[{ text: systemPrompt }] } })
      });

      const result = await resp.json();
      const text = result?.candidates?.[0]?.content?.parts?.[0]?.text || "";
      const m = text.match(/AURA_FETCH_PRODUCTS_JSON::\s*(\{[\s\S]*\})/);
      if (m) {
        try {
          const payload = JSON.parse(m[1]);
          const terms = Array.isArray(payload?.terms) ? payload.terms.filter(Boolean) : [];
          if (terms.length) { return fetchProducts(terms); }
        } catch(_e){}
      }
      return fallback();
    } catch(e){
      console.warn('Gemini error', e);
      return fallback();
    }
  }

  async function fetchProducts(searchInput){
    const formData = new FormData();
    formData.append('action', 'aura_fetch_products');
    if (Array.isArray(searchInput)) { searchInput.forEach(t => formData.append('terms[]', t)); }
    else { formData.append('search_term', searchInput); }
    try {
      const res = await fetch(aura_chatbot_data.ajax_url, { method:'POST', body: formData });
      const json = await res.json();
      if (json.success && json.data.html){
        document.getElementById('product-placeholder').style.display = 'none';
        productGrid.innerHTML = json.data.html;
        addBotMessage("Here are some matches. Click a product to see details, or 'Customize' to add to your order.");
      } else {
        addBotMessage((json && json.data && json.data.message) || "No products matched that term.");
      }
    } catch(err){
      addBotMessage("An error occurred while fetching products.");
    }
  }

  async function sendOrderEmail(){
    const fd = new FormData();
    fd.append('action', 'aura_send_order');
    fd.append('order', JSON.stringify(orderSummary));
    
    // Append artwork details if the last item had one
    const lastItem = orderSummary.length > 0 ? orderSummary[orderSummary.length - 1] : null;
    if (lastItem && lastItem.artwork_attachment_id) {
        fd.append('artwork_attachment_id', lastItem.artwork_attachment_id);
    }
    if (lastItem && lastItem.artwork_file) {
        fd.append('artwork_filename', lastItem.artwork_file);
    }
    if (lastItem && lastItem.artwork_file_url) {
        fd.append('artwork_url', lastItem.artwork_file_url);
    }

    try {
      const res = await fetch(aura_chatbot_data.ajax_url, { method:'POST', body: fd });
      const json = await res.json();
      if (json.success) {
        addBotMessage("Your order has been sent! Our team will get back to you shortly.");
        orderSummary = []; // Clear order
        updateOrderCount();
        closeSidePanel();
      }
      else addBotMessage("Sorry, there was an error sending your order.");
    } catch(err){
      addBotMessage("A network error occurred. Please try again.");
    }
  }

  init();
});

// Global observer to initialize artwork custom select
(function(){
  // Initialize custom select for Artwork
  const init = (root)=>{
    const btn  = root.querySelector('#wz-art-btn');
    const list = root.querySelector('#wz-art-options');
    const sel  = document.getElementById('wz-art');
    const label= root.querySelector('#wz-art-btn .label');
    if (!btn || !list || btn.__ac_init__) return;

    const close = ()=>{ list.classList.add('hidden'); btn.setAttribute('aria-expanded','false'); };
    const open  = ()=>{ list.classList.remove('hidden'); btn.setAttribute('aria-expanded','true'); };

    btn.addEventListener('click', (e)=>{
      e.stopPropagation();
      if (list.classList.contains('hidden')) open(); else close();
    });

    list.querySelectorAll('.ac-option').forEach(opt=>{
      opt.addEventListener('click', ()=>{
        const val = opt.getAttribute('data-value') || opt.textContent.trim();
        const _sel = sel || document.getElementById('wz-art'); if (_sel) _sel.value = val;
        if (label) label.textContent = val || 'Select...';
        list.querySelectorAll('.ac-option').forEach(o=>o.removeAttribute('aria-selected'));
        opt.setAttribute('aria-selected','true');
        close();
        if (typeof window.wizard !== 'undefined' && window.wizard.data) {
          window.wizard.data.artwork = val || '';
        }
        const wrap = document.getElementById('wz-art-upload-wrap');
        if (wrap) wrap.style.display = (val && val.toLowerCase() === 'upload artwork') ? 'block' : 'none';
      });
    });

    document.addEventListener('click', (e)=>{ if(!root.contains(e.target)) close(); });
    btn.__ac_init__ = true;
  };

  // Observe DOM for artwork select blocks
  const obs = new MutationObserver((ms)=>{
    ms.forEach(m=>{
      (m.addedNodes || []).forEach(node=>{
        if (!(node instanceof HTMLElement)) return;
        const roots = [];
        if (node.matches && node.matches('.ac-select-custom[data-select="wz-art"]')) roots.push(node);
        if (node.querySelectorAll) roots.push(...node.querySelectorAll('.ac-select-custom[data-select="wz-art"]'));
        roots.forEach(root=>{ if(!root.__ac_init__){ root.__ac_init__=true; init(root); } });
      });
    });
  });
  obs.observe(document.body, {childList:true, subtree:true});

  // Initialize existing ones on load
  document.querySelectorAll('.ac-select-custom[data-select="wz-art"]').forEach(init);

  // Fallback: toggle upload area when native select changes
  document.addEventListener('change', (ev)=>{
    const sel = ev.target && ev.target.matches && ev.target.matches('#wz-art') ? ev.target : null;
    if(!sel) return;
    const wrap = document.getElementById('wz-art-upload-wrap');
    if(!wrap) return;
    wrap.style.display = ((sel.value||'').toLowerCase() === 'upload artwork') ? 'block' : 'none';
  });

  // Global upload helper (unchanged API)
  window.auraUploadArtwork = function(file){
    return new Promise((resolve)=>{
      if(!(file instanceof Blob)) return resolve({ok:false, message:'Invalid file'});
      const max = 10 * 1024 * 1024;
      if (file.size > max) return resolve({ok:false, message:'File exceeds 10MB limit'});

      const statusEl = document.getElementById('wz-art-upload-status');
      if (statusEl) statusEl.textContent = 'Uploading...';

      const form = new FormData();
      form.append('action','aura_upload_artwork');
      form.append('artwork', file, file.name || 'artwork');

      let ajaxUrl = (window.aura_chatbot_data && window.aura_chatbot_data.ajax_url) ||
                    (window.AURA_VARS && window.AURA_VARS.ajaxUrl) ||
                    '/wp-admin/admin-ajax.php';

      const xhr = new XMLHttpRequest();
      xhr.open('POST', ajaxUrl);
      xhr.responseType = 'json';

      xhr.onload = ()=>{
        const res = xhr.response || {};
        if (statusEl) statusEl.textContent = '';
        if (res && res.success) resolve({ok:true, ...(res.data||{})});
        else resolve({ok:false, message:(res && res.data && res.data.message) || 'Upload failed'});
      };
      xhr.onerror = ()=>{
        if (statusEl) statusEl.textContent = 'Upload error';
        resolve({ok:false, message:'Network error'});
      };
      xhr.send(form);
    });
  };
})();

// Toggle uploader when 'Upload Artwork' is selected (fallback)
document.addEventListener('change', (ev)=>{
  const sel = ev.target && ev.target.matches && ev.target.matches('#wz-art') ? ev.target : null;
  if(!sel) return;
  const wrap = document.getElementById('wz-art-upload-wrap');
  if(!wrap) return;
  wrap.style.display = ((sel.value||'').toLowerCase() === 'upload artwork') ? 'block' : 'none';
});


// Make auraUploadArtwork global
window.auraUploadArtwork = function(file){
  return new Promise((resolve)=>{
    if(!file) return resolve({ok:false, message:'No file selected'});
    const max = 10 * 1024 * 1024; // 10MB
    if(file.size > max) return resolve({ok:false, message:'File exceeds 10MB limit'});
    let _nonce = '';
    try{ _nonce = (window.aura_chatbot_data && aura_chatbot_data.upload_nonce) || ''; }catch(e){}
    const form = new FormData();
    form.append('action','aura_upload_artwork');
    if (file instanceof Blob) { form.append('artwork', file, file.name || 'artwork'); } else { return resolve({ok:false, message:'Invalid file'}); }
    if(_nonce){ form.append('upload_nonce', _nonce); }

    const statusEl = document.getElementById('wz-art-upload-status');
    const xhr = new XMLHttpRequest();
    const ajaxUrl = (window.aura_chatbot_data && aura_chatbot_data.ajax_url) || '/wp-admin/admin-ajax.php';
    xhr.open('POST', ajaxUrl);
    xhr.responseType = 'json';

    if (xhr.upload && statusEl){
      xhr.upload.onprogress = (e)=>{
        if (e.lengthComputable){
          const pct = Math.round((e.loaded / e.total) * 100);
          statusEl.textContent = 'Uploading&hellip; ' + pct + '%';
        }
      };
    }

    xhr.onload = ()=>{
      const res = xhr.response || {};
      if (statusEl) statusEl.textContent = '';
      if (res && res.success) resolve({ok:true, ...res.data});
      else resolve({ok:false, message:(res && res.data && res.data.message) || 'Upload failed'});
    };
    xhr.onerror = ()=>{
      if (statusEl) statusEl.textContent = 'Upload error';
      resolve({ok:false, message:'Network error'});
    };
    xhr.send(form);
  });
}


/* AURA UPLOAD UI PATCH v2.1.3 */
function AURA_initUploadPatch(){
  function ensureUploadUI() {
    var artPane = document.getElementById('wz-art-upload-wrap');
    if (!artPane) return;
    var file = document.getElementById('wz-art-file');
    if (!file) return;
    
    var status = document.getElementById('wz-art-upload-status');
    if (!status) {
      status = document.createElement('div');
      status.id = 'wz-art-upload-status';
      status.className = 'text-xs text-gray-400 mt-2';
      // Insert after the file input's label
      file.closest('label.ac-file-btn').parentNode.insertBefore(status, file.closest('label.ac-file-btn').nextSibling);
    }
    
    var btn = document.getElementById('wz-art-upload-btn');
    if (!btn) {
      btn = document.createElement('button');
      btn.id = 'wz-art-upload-btn';
      btn.type = 'button';
      btn.textContent = 'Upload Now';
      btn.style.display = 'none'; // Hide by default
      btn.className = 'mt-3 px-3 py-2 rounded bg-white/10 text-white border border-white/20 hover:bg-white/15';
      status.parentNode.insertBefore(btn, status.nextSibling);
    }
    
    if (!file._auraUploadBound) {
        file.addEventListener('change', function() {
          btn.style.display = (file.files && file.files[0]) ? 'inline-flex' : 'none';
          status.textContent = ''; // Clear status on new file select
        });
        file._auraUploadBound = true;
    }
    
    if (!btn._auraBound) {
      btn.addEventListener('click', async function(ev) {
        ev.preventDefault();
        if (!file.files || !file.files[0]) { status.textContent = 'Please choose a file first.'; return; }
        btn.disabled = true;
        status.textContent = 'Uploading...';
        try {
          const result = await (typeof window.auraUploadArtwork === 'function' ? window.auraUploadArtwork(file.files[0]) : Promise.resolve({ok:false, message:'Uploader not found'}));
          btn.disabled = false;
          if (result && result.ok) {
            status.textContent = 'Uploaded: ' + (result.filename || 'file') + (result.url ? ' \u2713' : ''); // Checkmark
            if (window.wizard && window.wizard.data) {
              window.wizard.data.artwork = 'Upload Artwork';
              window.wizard.data.artwork_url = result.url || '';
              window.wizard.data.artwork_filename = result.filename || '';
              window.wizard.data.artwork_attachment_id = result.attachment_id || 0;
            }
            btn.style.display = 'none'; // Hide on success
          } else {
            status.textContent = (result && result.message) ? ('Error: ' + result.message) : 'Upload failed';
          }
        } catch(e) {
          btn.disabled = false;
          status.textContent = e && e.message ? e.message : 'Upload error';
        }
      });
      btn._auraBound = true;
    }
  }
  
  var tried = 0;
  var iv = setInterval(function(){
    tried++;
    ensureUploadUI();
    if (tried > 40) clearInterval(iv); // Stop after 20s
  }, 500);
  
  try {
    var mo = new MutationObserver(function(){ ensureUploadUI(); });
    mo.observe(document.documentElement, { childList: true, subtree: true });
  } catch(_){}
}
AURA_initUploadPatch();


function AURA_enhanceUploadPatch(){
  function enhanceUploadUI(){
    var artPane = document.getElementById('wz-art-upload-wrap');
    if (!artPane) return;
    var file = document.getElementById('wz-art-file');
    if (!file) return;
    
    // Filename field (readonly)
    var nameField = document.getElementById('wz-art-file-name');
    if (!nameField){
      nameField = document.createElement('input');
      nameField.id = 'wz-art-file-name';
      nameField.type = 'text';
      nameField.readOnly = true;
      nameField.placeholder = 'No file selected';
      nameField.className = 'mt-2 w-full px-3 py-2 rounded border border-white/20 bg-white/5 text-white text-sm';
      // insert just after the file input's label
      var label = file.closest('label.ac-file-btn');
      if (label) {
          label.parentNode.insertBefore(nameField, label.nextSibling);
      }
    }
    
    // Update when a file is chosen
    if (!file._auraNameBound){
      file.addEventListener('change', function(){
        nameField.value = (file.files && file.files[0]) ? file.files[0].name : '';
      });
      file._auraNameBound = true;
    }
  }

  // run soon and on DOM changes
  enhanceUploadUI();
  var tries = 0, iv = setInterval(function(){ enhanceUploadUI(); if (++tries > 40) clearInterval(iv); }, 500);
  try {
    var mo = new MutationObserver(function(){ enhanceUploadUI(); });
    mo.observe(document.documentElement, { childList: true, subtree: true });
  } catch(_){}
}
AURA_enhanceUploadPatch();



/* AURA v2.1.8 patches */
(function(){
  window.auraNotifyChat = window.auraNotifyChat || function (msg) {
    try {
      if (typeof window.auraAppendAssistantMessage === 'function') { window.auraAppendAssistantMessage(msg); return; }
      if (window.auraChat && typeof window.auraChat.postSystemMessage === 'function') { window.auraChat.postSystemMessage(msg); return; }
      var log = document.querySelector('[data-chat-log], #aura-chat-log, .aura-chat-log');
      if (log) { var el=document.createElement('div'); el.className='aura-system-line text-xs opacity-80 my-1'; el.textContent=msg; log.appendChild(el); log.scrollTop = log.scrollHeight; }
    } catch(_){}
  };

  if (typeof window.auraUploadArtwork === 'function' && !window._auraUploadNotifyWrap) {
    const _orig = window.auraUploadArtwork;
    window.auraUploadArtwork = async function(file){
      const res = await _orig(file);
      if (res && res.ok) { window.auraNotifyChat && window.auraNotifyChat('\u2705 Artwork uploaded: ' + (res.filename || 'file')); }
      return res;
    };
    window._auraUploadNotifyWrap = true;
  }

  function ensureOrderSummaryFilename(){
    var wiz = window.wizard;
    if (!wiz || !wiz.data || !wiz.data.artwork_filename) return;
    // Check review step, not order panel
    var container = document.querySelector('#order-summary-lines');
    if (!container) return;
    // Check if artwork row already shows filename
    var artRow = Array.from(container.children).find(el => el.textContent.includes('Artwork:'));
    if (artRow && artRow.textContent.includes(wiz.data.artwork_filename)) return; 
    
    // Update the artwork row directly if it exists
    if(artRow) {
        artRow.innerHTML = `<strong>Artwork:</strong> Uploaded: ${wiz.data.artwork_filename}`;
    }
  }
  var tries=0, iv=setInterval(function(){ ensureOrderSummaryFilename(); if(++tries>60) clearInterval(iv); }, 500);
  try{ var mo=new MutationObserver(ensureOrderSummaryFilename); mo.observe(document.documentElement,{childList:true,subtree:true}); }catch(_){}

  if (!window._auraOrderPayloadWrap) {
    const _fetch = window.fetch;
    window.fetch = function(input, init){
      try {
        if (init && init.body instanceof FormData && init.body.get('action') === 'aura_send_order') {
          // This patch is now redundant, as sendOrderEmail() appends the data directly.
          // Kept for compatibility if old code calls fetch directly.
          var wiz = window.wizard && window.wizard.data ? window.wizard.data : null;
          if (wiz) {
            if (wiz.artwork_filename && !init.body.get('artwork_filename')) init.body.append('artwork_filename', wiz.artwork_filename);
            if (wiz.artwork_url && !init.body.get('artwork_url')) init.body.append('artwork_url', wiz.artwork_url);
            if (wiz.artwork_attachment_id && !init.body.get('artwork_attachment_id')) init.body.append('artwork_attachment_id', wiz.artwork_attachment_id);
          }
        }
      } catch(_){}
      return _fetch.apply(this, arguments);
    };
    window._auraOrderPayloadWrap = true;
  }
})();