window.engine = new URLSearchParams(location.search).get('engine') || 'unknown';
window.wait = ms => new Promise(r => setTimeout(r, ms));
window.SC = '<scr' + 'ipt'; window.SE = '</scr' + 'ipt>';
window.post = (name, data) => fetch('/results?name=' + name, { method: 'POST', body: JSON.stringify(data) });
window.loadData = async () => (await fetch('/theme/assets/data/svg-motion/presets.json')).json();
