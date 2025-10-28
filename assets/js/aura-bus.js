/*! Aura Chat – Communication Layer v2.2.0 */
(function(){
  if (window.AuraBus) return;
  class Bus {
    constructor(){ this.events = {}; }
    on(evt, handler){ (this.events[evt] ||= []).push(handler); return () => this.off(evt, handler); }
    off(evt, handler){ const arr = this.events[evt] || []; const i = arr.indexOf(handler); if (i>-1) arr.splice(i,1); }
    emit(evt, payload){ (this.events[evt] || []).forEach(h => { try { h(payload);} catch(e){ console.error('AuraBus handler error', e);} }); }
    once(evt, handler){ const off = this.on(evt, (p)=>{ off(); handler(p); }); }
  }
  const bus = new Bus();
  window.AuraBus = bus;

  // Config hydration (set by wp_localize_script or inline config)
  window.AuraConfig = window.AuraConfig || {};
  // Helper for modules to await config
  window.AuraReady = function(fn){
    if (typeof fn === 'function') { try { fn(window.AuraConfig, window.AuraBus); } catch(e){ console.error(e); } }
  };
  // Simple request helper using WP REST with nonce if available
  window.AuraRequest = async function(path, opts){
    opts = opts || {};
    opts.headers = opts.headers || {};
    if (window.AuraConfig && window.AuraConfig.restNonce) {
      opts.headers['X-WP-Nonce'] = window.AuraConfig.restNonce;
    }
    const base = (window.AuraConfig && window.AuraConfig.restBase) || '/wp-json/';
    const res = await fetch(base.replace(/\/$/,'') + '/' + path.replace(/^\//,''), opts);
    if (!res.ok) {
      const t = await res.text();
      throw new Error('AuraRequest failed ' + res.status + ': ' + t);
    }
    return res.json();
  };
})();
