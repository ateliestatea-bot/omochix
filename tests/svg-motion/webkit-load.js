// WebKit: frame rate against drawing load after a click, and after a frame is recreated. Run from tests/svg-motion: node webkit-load.js
const R=__dirname+'/';const {webkit}=require('playwright');const {start}=require('./server.js');const fs=require('fs');
(async()=>{const stop=start();const b=await webkit.launch();const p=await b.newPage({viewport:{width:1300,height:800}});await p.goto('http://127.0.0.1:8770/pages/effects.html?engine=x');await p.waitForFunction(()=>document.title==='READY');const out={clicked:{},unclicked:{}};
 for(const fx of ['translate-g','blur-svg','rotY-svg','shadow-svg'])for(const n of [0,200,1000,2000]){const pr=p.evaluate(([n,fx])=>cost(n,fx,640,360),[n,fx]);await p.waitForSelector('#f');await p.waitForTimeout(100);await p.mouse.click(620,20);const r=await pr;out.clicked[fx+'/'+n]=r}
 for(const fx of ['translate-g'])for(const n of [0,1000])out.unclicked[fx+'/'+n]=await p.evaluate(([n,fx])=>cost(n,fx,640,360),[n,fx]);
 // A new frame in the same page after an earlier frame was clicked.
 const pr=p.evaluate(()=>cost(0,'translate-g',640,360));await p.waitForSelector('#f');await p.waitForTimeout(100);await p.mouse.click(620,20);out.first=await pr;out.remountAfterClick=await p.evaluate(()=>cost(0,'translate-g',640,360));
 console.log(JSON.stringify(out));fs.writeFileSync(R+'results/webkit-load.json',JSON.stringify(out,null,1));await b.close();stop();process.exit(0)})();
