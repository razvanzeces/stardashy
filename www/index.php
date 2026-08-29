<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Stardashy — Starlink Monitoring</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' fill='black'/><circle cx='50' cy='50' r='12' fill='white'/></svg>">
<!-- vendored libs (www/assets/, fetched by install.sh) with CDN fallback -->
<script src="colos.js"></script>
<script src="assets/chart.umd.min.js"></script>
<script>if(!window.Chart)document.write('<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"><\/script>')</script>
<script src="assets/satellite.min.js"></script>
<script>if(!window.satellite)document.write('<script src="https://cdn.jsdelivr.net/npm/satellite.js@5.0.0/dist/satellite.min.js"><\/script>')</script>
<script src="assets/topojson-client.min.js"></script>
<script>if(!window.topojson)document.write('<script src="https://cdn.jsdelivr.net/npm/topojson-client@3.1.0/dist/topojson-client.min.js"><\/script>')</script>
<style>
@import url('https://fonts.cdnfonts.com/css/d-din');

:root{
  --bg:#000000;
  --line:rgba(255,255,255,.14);
  --line-soft:rgba(255,255,255,.07);
  --text:#ffffff;
  --text2:#8a8f98;
  --text3:#565b63;
  --dim:#9aa0a8;
  --red:#ff3b30;
  --ok:#ffffff;
  --good:#3fb950;
  --warn:#d6a01d;
}
*{margin:0;padding:0;box-sizing:border-box}
[hidden]{display:none !important}
html{-webkit-text-size-adjust:100%}
body{
  font-family:'D-DIN','D-DIN Exp','DIN Alternate','Bahnschrift','Helvetica Neue',Arial,sans-serif;
  background:var(--bg); color:var(--text);
  -webkit-font-smoothing:antialiased;
}
svg.ic{width:14px;height:14px;stroke:currentColor;stroke-width:1.6;fill:none;
  stroke-linecap:round;stroke-linejoin:round;vertical-align:-2px}

header{
  position:sticky; top:0; z-index:10;
  background:rgba(0,0,0,.85);
  backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px);
  border-bottom:1px solid var(--line);
}
.hwrap{max-width:1060px;margin:0 auto;padding:16px 24px 0;display:flex;align-items:center;gap:16px;flex-wrap:wrap;min-height:64px}
.brand{display:flex;flex-direction:column;align-items:flex-start;line-height:1}
.brand-top{font-size:22px;font-weight:700;letter-spacing:.42em;text-transform:uppercase}
.brand-sub{font-size:10px;font-weight:600;letter-spacing:.50em;text-transform:uppercase;color:var(--text2);margin-top:5px}
.hstatus{margin-left:auto;display:flex;align-items:center;gap:22px}

/* ---- debug / settings ---- */
.lock{
  display:flex;flex-direction:column;align-items:center;gap:16px;
  padding:56px 0 96px;border-bottom:1px solid var(--line-soft);
  transition:opacity .45s ease;
}
.lock.bye{opacity:0;pointer-events:none}
.lk-scene{width:min(340px,86%);height:auto;display:block;margin-bottom:6px}
.lk-scene .st{fill:#fff;animation:lkTw 3.2s ease-in-out infinite}
.lk-scene .st.s2{animation-delay:.7s}
.lk-scene .st.s3{animation-delay:1.3s}
.lk-scene .st.s4{animation-delay:1.9s}
.lk-scene .st.s5{animation-delay:2.5s}
.lk-scene .st.s6{animation-delay:.4s}
@keyframes lkTw{0%,100%{opacity:.15}50%{opacity:.85}}
.lk-scene .ground{stroke:rgba(255,255,255,.22);stroke-width:1}
.lk-scene .dishb{stroke:#fff;stroke-width:1.4;fill:none;stroke-linecap:round}
.lk-scene .sweep{
  transform-box:fill-box;transform-origin:50% 100%;
  animation:lkSweep 5.5s ease-in-out infinite;
}
.lk-scene .sweep line{stroke:rgba(255,255,255,.35);stroke-width:1;stroke-dasharray:2 4}
@keyframes lkSweep{0%,100%{transform:rotate(-38deg)}50%{transform:rotate(38deg)}}
.lk-scene .ring{
  fill:none;stroke:#fff;stroke-width:1;
  transform-box:fill-box;transform-origin:center;
  animation:lkRing 3.4s ease-out infinite;
}
.lk-scene .ring.r2{animation-delay:1.7s}
@keyframes lkRing{0%{transform:scale(.35);opacity:.6}80%{opacity:0}100%{transform:scale(4.2);opacity:0}}
.lk-scene .sat rect{fill:#c8ccd2}
.lk-scene .sat .pn{fill:#565b63}
@keyframes lkShake{
  0%,100%{transform:translateX(0)}
  20%{transform:translateX(-8px)}40%{transform:translateX(7px)}
  60%{transform:translateX(-5px)}80%{transform:translateX(3px)}
}
.lock input.no{animation:lkShake .4s ease;border-color:var(--red)}
.lock .lk-t{font-size:11px;letter-spacing:.26em;text-transform:uppercase;color:var(--text2)}
.lock input{width:min(300px,80%)}
.lock .lk-err{font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:var(--red);min-height:14px}

input[type=text],input[type=password],input[type=number],select{
  appearance:none;background:transparent;border:1px solid var(--line);
  color:var(--text);font:inherit;font-size:13px;padding:9px 12px;border-radius:2px;
  letter-spacing:.04em;outline:none;font-variant-numeric:tabular-nums;
}
input:focus,select:focus{border-color:var(--text2)}
input::placeholder{color:var(--text3);letter-spacing:.1em;text-transform:uppercase;font-size:11px}
select{text-transform:uppercase;letter-spacing:.12em;font-size:11px}
select option{background:#0a0a0a}

.btn{
  appearance:none;background:transparent;border:1px solid var(--text);
  color:var(--text);font:inherit;font-size:11px;font-weight:600;
  letter-spacing:.18em;text-transform:uppercase;padding:9px 20px;border-radius:2px;
  cursor:pointer;transition:background .15s ease,color .15s ease;
}
.btn:hover{background:#fff;color:#000}
.btn.sec{border-color:var(--text3);color:var(--text2)}
.btn.sec:hover{background:var(--text3);color:#000}
.btn:disabled{opacity:.35;cursor:default}
.btn:disabled:hover{background:transparent;color:var(--text)}

.toolbar{display:flex;flex-wrap:wrap;gap:10px;padding:24px 2px 8px;align-items:center}
.toolbar input[type=text]{flex:1;min-width:220px}
.toolstatus{font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:var(--text3);
  padding:6px 2px 0;min-height:16px}

/* hop chain */
.hops{padding:14px 2px}
.hop{display:flex;gap:16px}
.hop .rail{display:flex;flex-direction:column;align-items:center;width:22px;flex:none}
.hop .rail .nd{width:9px;height:9px;border:1.5px solid var(--text);border-radius:50%;
  background:#000;flex:none;margin-top:16px}
.hop .rail .nd.lost{border-color:var(--red)}
.hop .rail .ln{width:1px;flex:1;background:var(--line)}
.hop:last-child .rail .ln{background:transparent}
.hop .card{flex:1;padding:12px 0 18px;min-width:0}
.hop .h-top{display:flex;flex-wrap:wrap;align-items:baseline;gap:8px 14px}
.hop .h-n{font-size:10px;letter-spacing:.2em;color:var(--text3)}
.hop .h-ip{font-size:14px;font-weight:600;letter-spacing:.02em}
.hop .h-ptr{font-size:11px;color:var(--text3);letter-spacing:.03em;overflow:hidden;
  text-overflow:ellipsis;white-space:nowrap;max-width:44ch}
.asn{
  font-size:9px;font-weight:700;letter-spacing:.12em;border:1px solid var(--text3);
  border-radius:2px;padding:2px 7px;color:var(--text2);white-space:nowrap;
}
.asn b{color:var(--text)}
.hop .h-stats{display:flex;gap:20px;margin-top:8px;font-size:11px;color:var(--text3);
  letter-spacing:.08em;text-transform:uppercase;font-variant-numeric:tabular-nums;flex-wrap:wrap}
.hop .h-stats b{color:var(--text);font-weight:600}
.hop .h-stats .lossy b{color:var(--red)}
.hbar{position:relative;height:3px;background:rgba(255,255,255,.08);margin-top:10px;max-width:520px}
.hbar .f{position:absolute;left:0;top:0;bottom:0;background:#fff}
.hbar .l{position:absolute;right:0;top:0;bottom:0;background:var(--red)}

/* http waterfall */
.wf{padding:16px 2px;max-width:640px}
.wf .row{display:flex;align-items:center;gap:12px;margin:7px 0}
.wf .lbl{width:74px;font-size:10px;letter-spacing:.16em;text-transform:uppercase;
  color:var(--text3);text-align:right;flex:none}
.wf .track{flex:1;height:14px;position:relative;background:rgba(255,255,255,.05)}
.wf .seg{position:absolute;top:0;bottom:0;background:#fff}
.wf .seg.dim{background:var(--dim)}
.wf .seg.dark{background:var(--text3)}
.wf .ms{width:80px;font-size:11px;color:var(--text2);font-variant-numeric:tabular-nums;flex:none}

/* settings */
.set-grid{display:grid;grid-template-columns:220px 1fr;gap:14px 22px;
  padding:20px 2px;align-items:center;max-width:760px}
.set-grid .k{font-size:10px;letter-spacing:.18em;text-transform:uppercase;color:var(--text2)}
.set-grid .k small{display:block;letter-spacing:.08em;color:var(--text3);margin-top:3px;text-transform:none}
.set-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.tgl{display:inline-flex;align-items:center;gap:10px;cursor:pointer;user-select:none}
.tgl input{display:none}
.tgl .bx{width:15px;height:15px;border:1px solid var(--text3);border-radius:2px;
  display:inline-flex;align-items:center;justify-content:center;flex:none;
  transition:border-color .15s ease}
.tgl input:checked + .bx{border-color:var(--text)}
.tgl input:checked + .bx::after{content:'';width:7px;height:7px;background:var(--text)}
.tgl .tl{font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:var(--text2)}
.provgrid{display:flex;flex-wrap:wrap;gap:8px}
.provgrid button{
  appearance:none;background:transparent;border:1px solid var(--line);color:var(--text3);
  font:inherit;font-size:10px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;
  padding:7px 13px;border-radius:2px;cursor:pointer;transition:all .15s ease;
}
.provgrid button:hover{border-color:var(--text2);color:var(--text2)}
.provgrid button.on{border-color:var(--text);color:var(--text);background:rgba(255,255,255,.06)}
.tgtlist{display:flex;flex-direction:column;gap:6px;margin-top:10px}
.tgtrow{display:flex;align-items:center;gap:9px;font-size:11px;
  letter-spacing:.06em;color:var(--text2);font-variant-numeric:tabular-nums}
.tgtrow .nm{min-width:112px;text-transform:uppercase;font-size:10px;letter-spacing:.14em}
.tgtrow .ip{color:var(--text3)}
.tgtrow .rm{margin-left:auto;appearance:none;background:transparent;border:0;
  color:var(--text3);font:inherit;font-size:15px;cursor:pointer;padding:0 6px;line-height:1}
.tgtrow .rm:hover{color:var(--red)}
.chatpick{display:flex;flex-direction:column;gap:6px;padding-top:6px}
.chatpick button{
  appearance:none;background:transparent;border:1px solid var(--line);color:var(--text2);
  font:inherit;font-size:11px;letter-spacing:.06em;padding:7px 12px;border-radius:2px;
  cursor:pointer;text-align:left;
}
.chatpick button:hover{border-color:var(--text2);color:var(--text)}
.savebar{display:flex;gap:14px;align-items:center;padding:10px 2px 30px}

@media (max-width:820px){
  .brand-top{font-size:16px}
  .brand-sub{font-size:8px}
  .set-grid{grid-template-columns:1fr}
}
/* ---- intro splash ---- */
#splash{position:fixed;inset:0;z-index:100;background:#000;display:flex;
  flex-direction:column;align-items:center;justify-content:center;gap:26px;
  transition:opacity .6s ease}
#splash.bye{opacity:0;pointer-events:none}
#splash .lk-scene{margin-bottom:0}
#splash .sp-t{font-size:clamp(22px,4.5vw,34px);font-weight:700;letter-spacing:.5em;
  text-transform:uppercase;padding-left:.5em;animation:spIn 1s ease both}
#splash .sp-s{font-size:11px;font-weight:600;letter-spacing:.6em;text-transform:uppercase;
  color:var(--text2);padding-left:.6em;margin-top:8px;text-align:center;
  animation:spIn 1s .2s ease both}
#splash .sp-bar{width:min(220px,50%);height:1px;background:rgba(255,255,255,.12);
  overflow:hidden;margin-top:18px}
#splash .sp-bar i{display:block;height:100%;width:40%;background:#fff;
  animation:spBar 1.1s ease-in-out infinite}
@keyframes spIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
@keyframes spBar{0%{transform:translateX(-100%)}100%{transform:translateX(350%)}}

/* ---- energy ---- */
.enhero .num{color:var(--text)}
.watt{position:relative;height:8px;background:rgba(255,255,255,.07);margin-top:16px}
.watt i{position:absolute;top:0;bottom:0;left:0;background:var(--text);transition:width .5s ease}
.watt i.heat{background:var(--warn)}
.watt s{position:absolute;top:-4px;bottom:-4px;width:1px;background:var(--text3);
  text-decoration:none}
.heatpill{display:inline-flex;align-items:center;gap:7px;margin-top:12px;
  font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:var(--warn);
  border:1px solid var(--warn);border-radius:2px;padding:3px 10px}
.heatpill.off{display:none}
.costgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0}
.enote{font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:var(--text3);
  padding:14px 2px 0;display:flex;gap:18px;flex-wrap:wrap}
.enote b{color:var(--text2)}

/* ---- throttle badge ---- */
.throttle{display:none;align-items:center;gap:7px;font-size:10px;font-weight:600;
  letter-spacing:.14em;text-transform:uppercase;color:var(--warn);
  border:1px solid var(--warn);border-radius:2px;padding:3px 9px}
.throttle.on{display:inline-flex}

/* ---- data usage ---- */
.ubar{height:6px;background:rgba(255,255,255,.08);margin-top:14px;position:relative;overflow:hidden}
.ubar i{position:absolute;left:0;top:0;bottom:0;background:var(--text);transition:width .4s ease}
.ubar i.warn{background:var(--warn)}
.ubar i.bad{background:var(--red)}
.ubar u{position:absolute;top:-3px;bottom:-3px;width:1px;background:var(--text3)}
.unote{font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:var(--text3);
  margin-top:9px;display:flex;gap:16px;flex-wrap:wrap}
.unote b{color:var(--text2)}
.unote .warn{color:var(--warn)}

/* ---- outage timeline ---- */
.causebar{display:flex;flex-wrap:wrap;gap:8px 22px;padding:16px 2px 2px;
  font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:var(--text3)}
.causebar span{display:flex;align-items:center;gap:8px}
.causebar b{color:var(--text);font-weight:600;font-variant-numeric:tabular-nums}
.causebar i{width:9px;height:9px;border-radius:1px;background:var(--red);flex:none}
.causebar i.deg{background:var(--warn)}
.causebar i.blind{background:#4a4f57}
.tlwrap{padding:20px 2px 4px}
#tlCanvas{width:100%;height:58px;display:block;cursor:crosshair}
.tlaxis{display:flex;justify-content:space-between;font-size:9px;letter-spacing:.12em;
  text-transform:uppercase;color:var(--text3);margin-top:7px;font-variant-numeric:tabular-nums}
.tlkey{display:flex;gap:18px;flex-wrap:wrap;margin-top:14px;font-size:10px;
  letter-spacing:.12em;text-transform:uppercase;color:var(--text3)}
.tlkey span{display:flex;align-items:center;gap:7px}
.tlkey i{width:11px;height:11px;display:inline-block;border-radius:1px}
.tlcap{margin-top:12px;min-height:16px;font-size:11px;letter-spacing:.1em;
  text-transform:uppercase;color:var(--text3);font-variant-numeric:tabular-nums}
.tlcap b{color:var(--text)}
.evkind{font-size:9px;font-weight:700;letter-spacing:.12em;border:1px solid var(--text3);
  border-radius:2px;padding:2px 8px;color:var(--text2);white-space:nowrap}
.evkind.link{border-color:var(--red);color:var(--red)}
.evkind.blind{border-color:var(--text3);color:var(--text3)}
.evkind.deg{border-color:var(--warn);color:var(--warn)}

/* ---- dish selector ---- */
/* Hidden entirely with one dish, which is the overwhelmingly common case —
   a chooser with a single option is just clutter. */
.dishsel{display:none;align-items:stretch;border:1px solid var(--line);
  border-radius:2px;overflow:hidden;line-height:1}
.dishsel.on{display:inline-flex}
.dishsel .lbl{display:flex;align-items:center;padding:0 9px;
  background:rgba(255,255,255,.05);border-right:1px solid var(--line);
  font-size:9px;font-weight:600;letter-spacing:.16em;text-transform:uppercase;
  color:var(--text3);white-space:nowrap}
.dishsel button{
  appearance:none;background:transparent;border:0;border-right:1px solid var(--line-soft);
  color:var(--text3);font:inherit;font-size:10px;font-weight:600;letter-spacing:.14em;
  text-transform:uppercase;padding:7px 13px;cursor:pointer;white-space:nowrap;
  transition:color .15s ease,background .15s ease}
.dishsel button:last-child{border-right:0}
.dishsel button:hover{color:var(--text2)}
.dishsel button.active{color:var(--text);background:rgba(255,255,255,.08)}

/* ---- test edge chip ---- */
/* Labelled, because "SOFIA · SOF · 438 KM" on its own means nothing to
   anyone who does not already know what a Cloudflare colo code is. */
.popbox{
  display:inline-flex;align-items:stretch;border:1px solid var(--line);
  border-radius:2px;overflow:hidden;line-height:1;cursor:default;
}
.popbox .lbl{
  display:flex;align-items:center;padding:0 9px;
  background:rgba(255,255,255,.05);border-right:1px solid var(--line);
  font-size:9px;font-weight:600;letter-spacing:.16em;text-transform:uppercase;
  color:var(--text3);white-space:nowrap;
}
.popbox .val{
  display:flex;align-items:center;gap:8px;padding:6px 11px;
  font-size:11px;letter-spacing:.1em;white-space:nowrap;
}
.popbox .flag{font-size:14px;line-height:1;letter-spacing:0;filter:saturate(.92)}
.popbox b{color:var(--text);font-weight:600;text-transform:uppercase;letter-spacing:.12em}
.popbox .popsub{color:var(--text3);font-size:10px;letter-spacing:.1em;
  font-variant-numeric:tabular-nums}
.popbox .popsub b{color:var(--text2);font-weight:600}
@media (max-width:820px){
  .popbox .lbl{display:none}
  .popbox .popsub{display:none}
}

/* ---- hero deltas ---- */
.dlt{font-size:11px;letter-spacing:.1em;margin-left:9px;font-variant-numeric:tabular-nums;
  white-space:nowrap;vertical-align:2px}
.dlt.up{color:var(--good)}
.dlt.dn{color:var(--red)}
.dlt.flat{color:var(--text3)}

/* ---- ICMP health ---- */
.hz{display:grid;grid-template-columns:repeat(var(--hz-cols,3),minmax(0,1fr));gap:0}
.hz-t{
  padding:16px 20px 15px;border-bottom:1px solid var(--line-soft);
  position:relative;overflow:hidden;
}
.hz-t{border-left:1px solid var(--line-soft)}
.hz-t.rowstart{border-left:0}
.hz-hd{display:flex;align-items:center;gap:8px;min-width:0}
.hz-dot{width:7px;height:7px;border-radius:50%;flex:none;background:var(--text3)}
.hz-dot.good{background:var(--good);box-shadow:0 0 7px var(--good);animation:hzP 2.4s ease-in-out infinite}
.hz-dot.warn{background:var(--warn);box-shadow:0 0 7px var(--warn);animation:hzP 1.4s ease-in-out infinite}
.hz-dot.bad {background:var(--red); box-shadow:0 0 7px var(--red); animation:hzP .8s ease-in-out infinite}
.hz-dot.down{background:var(--red);animation:hzB .7s steps(1) infinite}
.hz-dot.stale{background:var(--text3)}
@keyframes hzP{0%,100%{opacity:1}50%{opacity:.45}}
@keyframes hzB{0%,49%{opacity:1}50%,100%{opacity:.12}}
.hz-nm{font-size:10px;font-weight:600;letter-spacing:.18em;text-transform:uppercase;
  color:var(--text2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.hz-ip{font-size:9px;letter-spacing:.1em;color:var(--text3);margin-left:auto;
  font-variant-numeric:tabular-nums;white-space:nowrap}
.hz-v{display:flex;align-items:baseline;gap:6px;margin-top:9px}
.hz-ms{font-size:29px;font-weight:400;line-height:1;font-variant-numeric:tabular-nums;
  color:var(--text);transition:color .3s ease}
.hz-ms.good{color:var(--good)}
.hz-ms.warn{color:var(--warn)}
.hz-ms.bad,.hz-ms.down{color:var(--red)}
.hz-ms.stale{color:var(--text3)}
.hz-u{font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:var(--text3)}
.hz-strip{width:100%;height:34px;display:block;margin-top:11px}
.hz-ft{display:flex;gap:12px;margin-top:8px;font-size:9px;letter-spacing:.1em;
  text-transform:uppercase;color:var(--text3);font-variant-numeric:tabular-nums;flex-wrap:wrap}
.hz-ft b{color:var(--text2);font-weight:600}
.hz-ft .lossy b{color:var(--red)}
.hz-empty{padding:26px 20px;color:var(--text3);font-size:11px;
  letter-spacing:.16em;text-transform:uppercase}

/* ---- update banner ---- */
#updBar{
  display:none;align-items:center;gap:14px;flex-wrap:wrap;
  max-width:1060px;margin:0 auto;padding:12px 24px;
  border-bottom:1px solid var(--line);
  font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:var(--text2);
  animation:updIn .5s ease both;
}
#updBar.on{display:flex}
@keyframes updIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:none}}
#updBar .dotnew{width:7px;height:7px;border-radius:50%;background:#fff;
  animation:blink 1.6s ease-in-out infinite;flex:none}
#updBar b{color:var(--text);font-weight:600;letter-spacing:.08em}
#updBar .spacer{margin-left:auto}
#updBar button{
  appearance:none;background:transparent;border:1px solid var(--text);color:var(--text);
  font:inherit;font-size:10px;font-weight:600;letter-spacing:.16em;text-transform:uppercase;
  padding:6px 14px;border-radius:2px;cursor:pointer;
}
#updBar button:hover{background:#fff;color:#000}
#updBar button.ghost{border-color:var(--text3);color:var(--text3)}
#updBar button.ghost:hover{background:transparent;border-color:var(--text2);color:var(--text2)}

/* ---- update panel (settings) ---- */
.updbox{padding:20px 2px}
.updrow{display:flex;align-items:baseline;gap:14px;flex-wrap:wrap;
  font-size:12px;letter-spacing:.1em;color:var(--text2);text-transform:uppercase}
.updrow b{color:var(--text);font-weight:600;font-variant-numeric:tabular-nums}
.updver{font-size:34px;font-weight:400;letter-spacing:-.01em;color:var(--text);
  font-variant-numeric:tabular-nums;text-transform:none}
.updnotes{
  margin-top:16px;padding:16px 18px;border:1px solid var(--line-soft);border-radius:2px;
  font-size:12px;line-height:1.6;color:var(--text2);letter-spacing:.02em;
  white-space:pre-wrap;max-height:230px;overflow:auto;
}
.updlog{margin-top:14px;font-size:11px;letter-spacing:.12em;text-transform:uppercase;
  color:var(--text3);min-height:16px}
.updlog.err{color:var(--red)}
.updlog.ok{color:var(--text)}

/* ---- footer ---- */
.foot{max-width:1060px;margin:0 auto;padding:0 24px 40px;display:flex;gap:10px;
  flex-wrap:wrap;font-size:10px;letter-spacing:.18em;text-transform:uppercase;
  color:var(--text3)}
.foot b{color:var(--text2);font-weight:600}

.hitem{display:flex;align-items:center;gap:8px;font-size:11px;letter-spacing:.14em;
  text-transform:uppercase;color:var(--text2)}
.hitem b{color:var(--text);font-weight:600;letter-spacing:.08em}
.sdot{width:8px;height:8px;border-radius:50%;background:var(--ok)}
.sdot.err{background:var(--red)}
.sdot.blink{animation:blink 1.6s ease-in-out infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.25}}

.nav{max-width:1060px;margin:0 auto;padding:10px 24px 0;display:flex;gap:0}
.nav button{
  appearance:none;border:0;background:transparent;color:var(--text3);
  font:inherit;font-size:11px;font-weight:600;letter-spacing:.18em;text-transform:uppercase;
  padding:10px 22px 12px;cursor:pointer;display:flex;align-items:center;gap:8px;
  border-bottom:2px solid transparent;
  transition:color .15s ease;
}
.nav button:hover{color:var(--text2)}
.nav button.active{color:var(--text);border-bottom-color:var(--text)}

main{max-width:1060px;margin:0 auto;padding:38px 24px 80px}
.view{display:none}
.view.active{display:block}

.sect{
  font-size:11px;font-weight:600;letter-spacing:.22em;text-transform:uppercase;
  color:var(--text2);
  padding-bottom:10px;margin:38px 0 0;
  border-bottom:1px solid var(--line);
  display:flex;align-items:center;gap:9px;
}
.sect:first-child{margin-top:0}
.sect small{font-weight:500;letter-spacing:.14em;color:var(--text3);margin-left:auto}

.hero{display:grid;grid-template-columns:1fr 1fr 1fr}
.metric{padding:26px 26px 24px;border-bottom:1px solid var(--line)}
.metric + .metric{border-left:1px solid var(--line-soft)}
.metric .label{
  font-size:11px;font-weight:600;letter-spacing:.2em;text-transform:uppercase;color:var(--text2);
  display:flex;align-items:center;gap:8px;
}
.num{
  font-size:clamp(48px,7vw,80px); font-weight:400; letter-spacing:-.01em;
  line-height:1.02; font-variant-numeric:tabular-nums; margin-top:10px;
}
.unit{font-size:15px;color:var(--text2);letter-spacing:.1em;text-transform:uppercase;margin-left:8px}
.sub{margin-top:8px;font-size:12px;color:var(--text3);letter-spacing:.06em;text-transform:uppercase}
.sub b{color:var(--text2);font-weight:600}

.grade{display:inline-flex;align-items:center;gap:10px;margin-top:12px}
.grade .pill{
  font-size:13px;font-weight:700;letter-spacing:.1em;
  border:1px solid var(--text);border-radius:2px;padding:2px 10px;color:var(--text);
}
.grade .pill.bad{border-color:var(--red);color:var(--red)}
.grade span{font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:var(--text3)}

.meta-line{
  display:flex;flex-wrap:wrap;gap:10px 34px;
  padding:14px 2px;border-bottom:1px solid var(--line-soft);
  font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:var(--text3);
}
.meta-line span{display:flex;align-items:center;gap:7px}
.meta-line b{color:var(--text2);font-weight:600}

.alert-row{
  display:none;align-items:center;gap:10px;
  padding:12px 2px;border-bottom:1px solid var(--line-soft);
  font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:var(--red);
}
.alert-row.on{display:flex}

.seg{display:flex;gap:0;margin:26px 0 0;border-bottom:1px solid var(--line)}
.seg button{
  appearance:none;border:0;background:transparent;color:var(--text3);
  font:inherit;font-size:11px;font-weight:600;letter-spacing:.18em;text-transform:uppercase;
  padding:10px 22px;cursor:pointer;
  border-bottom:2px solid transparent;margin-bottom:-1px;
  transition:color .15s ease;
}
.seg button:hover{color:var(--text2)}
.seg button.active{color:var(--text);border-bottom-color:var(--text)}

.chart-block{padding:22px 2px 6px}
.chart-wrap{position:relative;height:250px}
.chart-wrap.short{height:180px}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:0}
.grid2 > div + div{border-left:1px solid var(--line-soft);padding-left:26px}
.grid2 > div{padding-right:26px}
.chart-title{
  font-size:11px;font-weight:600;letter-spacing:.2em;text-transform:uppercase;color:var(--text2);
  margin-bottom:16px;display:flex;align-items:center;gap:8px;
}
.chart-title .livebadge{
  margin-left:auto;display:flex;align-items:center;gap:6px;
  font-size:10px;letter-spacing:.16em;color:var(--text3);
}

.usage-tests{display:grid;grid-template-columns:repeat(5,1fr);gap:0}
.utile{padding:18px 20px;border-bottom:1px solid var(--line-soft)}
.utile + .utile{border-left:1px solid var(--line-soft)}
.utile .t{font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:var(--text3)}
.utile .mb{font-size:26px;font-weight:400;margin-top:6px;font-variant-numeric:tabular-nums}
.utile .mb small{font-size:11px;color:var(--text2);letter-spacing:.1em;margin-left:4px}
.utile .split{font-size:10px;letter-spacing:.1em;color:var(--text3);margin-top:4px;
  font-variant-numeric:tabular-nums;text-transform:uppercase}

.usage-summary,.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:0}
.usum,.stat{padding:18px 20px;border-bottom:1px solid var(--line-soft)}
.usum + .usum,.stat + .stat{border-left:1px solid var(--line-soft)}
.stat:nth-child(5){border-left:0}
.usum .k,.stat .k{font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:var(--text3)}
.usum .v,.stat .v{font-size:26px;font-weight:400;margin-top:6px;font-variant-numeric:tabular-nums}
.usum .v small,.stat .v small{font-size:11px;color:var(--text2);letter-spacing:.1em;margin-left:4px}
.stat .v.bad{color:var(--red)}

.tblwrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-variant-numeric:tabular-nums;min-width:900px}
th{
  font-size:10px;font-weight:600;letter-spacing:.16em;text-transform:uppercase;color:var(--text3);
  text-align:left;padding:12px 14px 10px;border-bottom:1px solid var(--line);white-space:nowrap;
}
th.r, td.r{text-align:right}
td{
  font-size:13px;color:var(--text);padding:11px 14px;
  border-bottom:1px solid var(--line-soft);white-space:nowrap;
}
td .mut{color:var(--text3);font-size:11px;letter-spacing:.04em}
td.dim{color:var(--text2)}
tr:hover td{background:rgba(255,255,255,.03)}
.st-ok{font-size:10px;font-weight:700;letter-spacing:.14em;
  border:1px solid var(--text3);border-radius:2px;padding:2px 8px;color:var(--text2)}
.st-fail{font-size:10px;font-weight:700;letter-spacing:.14em;
  border:1px solid var(--red);border-radius:2px;padding:2px 8px;color:var(--red)}
.ip-current{font-size:9px;font-weight:700;letter-spacing:.14em;border:1px solid var(--text);
  border-radius:2px;padding:2px 7px;color:var(--text);margin-left:10px;vertical-align:1px}
.tnote{font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:var(--text3);padding:14px 2px}

.skywrap{padding:26px 2px 6px;display:flex;flex-direction:column;align-items:center}
#skyMap{width:100%;max-width:560px;height:auto;display:block;cursor:crosshair}
.geowrap{padding:26px 2px 6px}
#geoMap{width:100%;height:auto;display:block;cursor:crosshair;border:1px solid var(--line-soft)}
.skycap{
  margin-top:12px;font-size:11px;letter-spacing:.14em;text-transform:uppercase;
  color:var(--text3);font-variant-numeric:tabular-nums;min-height:16px;text-align:center;
}
.skycap b{color:var(--text);font-weight:600}

.empty{color:var(--text3);text-align:center;padding:80px 0;font-size:12px;
  letter-spacing:.2em;text-transform:uppercase}

@media (max-width:820px){
  .hero{grid-template-columns:1fr}
  .metric + .metric{border-left:0}
  .grid2{grid-template-columns:1fr}
  .grid2 > div + div{border-left:0;padding-left:0;margin-top:20px}
  .grid2 > div{padding-right:0}
  .usage-tests{grid-template-columns:1fr 1fr}
  .usage-summary,.stats{grid-template-columns:1fr 1fr}
  .hstatus{gap:14px}
  main{padding:24px 16px 60px}
  .nav button{padding:10px 12px 12px;letter-spacing:.1em}
}
</style>
</head>
<body>

<div id="splash" aria-hidden="true">
  <svg class="lk-scene" viewBox="0 0 280 150">
    <circle class="st"    cx="30"  cy="26" r="1"/>
    <circle class="st s2" cx="72"  cy="14" r="1"/>
    <circle class="st s3" cx="126" cy="30" r="1.2"/>
    <circle class="st s4" cx="182" cy="12" r="1"/>
    <circle class="st s5" cx="228" cy="28" r="1.2"/>
    <circle class="st s6" cx="256" cy="52" r="1"/>
    <g class="sweep"><line x1="140" y1="126" x2="140" y2="30"/></g>
    <circle class="ring"    cx="140" cy="123" r="7"/>
    <circle class="ring r2" cx="140" cy="123" r="7"/>
    <g class="sat">
      <animateMotion dur="10s" repeatCount="indefinite" path="M-24 46 Q140 4 304 42"/>
      <rect class="pn" x="-9" y="-1.4" width="5" height="2.8"/>
      <rect class="pn" x="4"  y="-1.4" width="5" height="2.8"/>
      <rect x="-3" y="-2.2" width="6" height="4.4"/>
    </g>
    <g class="sat">
      <animateMotion dur="15s" repeatCount="indefinite" path="M304 68 Q140 26 -24 64"/>
      <rect class="pn" x="-9" y="-1.4" width="5" height="2.8"/>
      <rect class="pn" x="4"  y="-1.4" width="5" height="2.8"/>
      <rect x="-3" y="-2.2" width="6" height="4.4"/>
    </g>
    <line class="ground" x1="0" y1="132" x2="280" y2="132"/>
    <path class="dishb" d="M131 120 Q140 131 149 121"/>
    <path class="dishb" d="M140 126 v6"/>
  </svg>
  <div class="sp-t">Starlink</div>
  <div class="sp-s">Monitoring</div>
  <div class="sp-bar"><i></i></div>
</div>

<header>
  <div class="hwrap">
    <div class="brand">
      <span class="brand-top">Starlink</span>
      <span class="brand-sub">Monitoring</span>
    </div>
    <div class="hstatus">
      <span class="dishsel" id="dishSel"><span class="lbl">Dish</span></span>
      <span class="popbox" id="popItem">
        <span class="lbl">Test Edge</span>
        <span class="val">
          <span class="flag" id="popFlag"></span>
          <b id="popName">—</b>
          <span class="popsub" id="popSub"></span>
        </span>
      </span>
      <span class="throttle" id="thrBadge" title="The dish reports it is being rate limited">
        <svg class="ic" viewBox="0 0 24 24" style="width:12px;height:12px"><path d="M12 3l9 16H3z"/><path d="M12 10v4M12 17h.01"/></svg>
        <span id="thrText"></span>
      </span>
      <span class="hitem"><span class="sdot" id="dot"></span><span id="lastRun">Connecting</span></span>
    </div>
  </div>
  <nav class="nav">
    <button data-view="dash" class="active">
      <svg class="ic" viewBox="0 0 24 24"><rect x="3" y="3" width="7.5" height="7.5"/><rect x="13.5" y="3" width="7.5" height="7.5"/><rect x="3" y="13.5" width="7.5" height="7.5"/><rect x="13.5" y="13.5" width="7.5" height="7.5"/></svg>
      Dashboard</button>
    <button data-view="dish">
      <svg class="ic" viewBox="0 0 24 24"><ellipse cx="12" cy="16" rx="8" ry="3.4"/><path d="M12 16V9"/><path d="M7.5 5.5a6.4 6.4 0 0 1 9 0"/><path d="M5.5 3.2a9.6 9.6 0 0 1 13 0"/></svg>
      Dish</button>
    <button data-view="sats">
      <svg class="ic" viewBox="0 0 24 24"><rect x="9" y="9" width="6" height="6" transform="rotate(45 12 12)"/><path d="M3.5 8.5l4 4M16.5 11.5l4 4M8.5 3.5l4 4M11.5 16.5l4 4"/></svg>
      Sats</button>
    <button data-view="energy">
      <svg class="ic" viewBox="0 0 24 24"><path d="M13 2L4 14h6l-1 8 9-12h-6l1-8z"/></svg>
      Energy</button>
    <button data-view="tests">
      <svg class="ic" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
      Tests</button>
    <button data-view="log">
      <svg class="ic" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>
      Log</button>
    <button data-view="debug">
      <svg class="ic" viewBox="0 0 24 24"><path d="M4 5h16v12H4z"/><path d="M7 9l3 2.5L7 14M12 14h5"/></svg>
      Debug</button>
    <button data-view="settings">
      <svg class="ic" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M19.1 4.9L17 7M7 17l-2.1 2.1"/></svg>
      Settings</button>
  </nav>
  <div id="updBar">
    <span class="dotnew"></span>
    <span id="updBarTxt">Update available</span>
    <span class="spacer"></span>
    <button id="updBarGo">View</button>
    <button id="updBarHide" class="ghost">Later</button>
  </div>
</header>

<main>

<!-- ============ DASHBOARD ============ -->
<section class="view active" id="view-dash">
  <div class="hero">
    <div class="metric">
      <div class="label"><svg class="ic" viewBox="0 0 24 24"><path d="M12 4v14M6 12l6 6 6-6"/></svg>Download</div>
      <div class="num"><span id="downNow">–</span><span class="unit">Mbps</span></div>
      <div class="sub" id="downSub">&nbsp;</div>
    </div>
    <div class="metric">
      <div class="label"><svg class="ic" viewBox="0 0 24 24"><path d="M12 20V6M6 12l6-6 6 6"/></svg>Upload</div>
      <div class="num"><span id="upNow">–</span><span class="unit">Mbps</span></div>
      <div class="sub" id="upSub">&nbsp;</div>
    </div>
    <div class="metric">
      <div class="label"><svg class="ic" viewBox="0 0 24 24"><path d="M4 18a9 9 0 0 1 16 0"/><path d="M12 13l4-5"/><circle cx="12" cy="14" r="1.4"/></svg>Latency</div>
      <div class="num"><span id="latNow">–</span><span class="unit">ms</span></div>
      <div class="sub" id="latSub">&nbsp;</div>
      <div class="grade" id="gradeWrap" hidden>
        <span class="pill" id="gradePill">–</span>
        <span id="gradeText">Bufferbloat</span>
      </div>
    </div>
  </div>

  <div class="meta-line" id="metaLine"></div>

  <div id="hzCard" hidden>
    <div class="sect">
      <svg class="ic" viewBox="0 0 24 24"><path d="M2 12h4l3-8 4 16 3-8h6"/></svg>
      ICMP Health<small id="hzNote"></small>
    </div>
    <div class="hz" id="hzGrid"></div>
  </div>

  <div class="seg" data-seg role="tablist" aria-label="Time range">
    <button data-range="3h">3H</button>
    <button data-range="24h" class="active">24H</button>
    <button data-range="7d">7D</button>
    <button data-range="30d">30D</button>
  </div>

  <div class="chart-block">
    <div class="chart-title"><svg class="ic" viewBox="0 0 24 24"><path d="M3 17l5-6 4 3 6-8 3 4"/></svg>Throughput</div>
    <div class="chart-wrap"><canvas id="chartTp"></canvas></div>
  </div>

  <div class="chart-block grid2">
    <div>
      <div class="chart-title"><svg class="ic" viewBox="0 0 24 24"><path d="M4 18a9 9 0 0 1 16 0"/><path d="M12 13l4-5"/></svg>Latency — Idle vs Loaded</div>
      <div class="chart-wrap short"><canvas id="chartLat"></canvas></div>
    </div>
    <div>
      <div class="chart-title"><svg class="ic" viewBox="0 0 24 24"><path d="M2 12h4l3-8 4 16 3-8h6"/></svg>Ping 1.1.1.1 / Packet Loss</div>
      <div class="chart-wrap short"><canvas id="chartPing"></canvas></div>
    </div>
  </div>

  <div id="usageCard" hidden>
    <div class="sect"><svg class="ic" viewBox="0 0 24 24"><path d="M21 8v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8"/><path d="M3 8l9-5 9 5"/><path d="M12 12v5M9.5 14.5L12 17l2.5-2.5"/></svg>Data Usage<small>Last 5 tests</small></div>
    <div class="usage-tests" id="usageTests"></div>
    <div class="usage-summary" id="usageSummary"></div>
  </div>

  <div class="sect"><svg class="ic" viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>Statistics</div>
  <div class="stats" id="statsGrid"></div>
</section>

<!-- ============ DISH ============ -->
<section class="view" id="view-dish">
  <div class="hero">
    <div class="metric">
      <div class="label"><svg class="ic" viewBox="0 0 24 24"><path d="M12 4v14M6 12l6 6 6-6"/></svg>Downlink</div>
      <div class="num"><span id="dDown">–</span><span class="unit">Mbps</span></div>
      <div class="sub" id="dDownSub">Realtime dish telemetry</div>
    </div>
    <div class="metric">
      <div class="label"><svg class="ic" viewBox="0 0 24 24"><path d="M12 20V6M6 12l6-6 6 6"/></svg>Uplink</div>
      <div class="num"><span id="dUp">–</span><span class="unit">Mbps</span></div>
      <div class="sub" id="dUpSub">Realtime dish telemetry</div>
    </div>
    <div class="metric">
      <div class="label"><svg class="ic" viewBox="0 0 24 24"><path d="M4 18a9 9 0 0 1 16 0"/><path d="M12 13l4-5"/><circle cx="12" cy="14" r="1.4"/></svg>PoP Latency</div>
      <div class="num"><span id="dPop">–</span><span class="unit">ms</span></div>
      <div class="sub" id="dPopSub">Dish to point of presence</div>
    </div>
  </div>

  <div class="alert-row" id="dishAlerts">
    <svg class="ic" viewBox="0 0 24 24"><path d="M12 3l9 16H3z"/><path d="M12 10v4M12 17h.01"/></svg>
    <span id="dishAlertsText"></span>
  </div>

  <div class="meta-line" id="dishMeta"></div>

  <div class="chart-block">
    <div class="chart-title">
      <svg class="ic" viewBox="0 0 24 24"><path d="M3 17l5-6 4 3 6-8 3 4"/></svg>Live Throughput
      <span class="livebadge"><span class="sdot blink" id="liveDot"></span><span id="liveBadgeTxt">LIVE</span></span>
    </div>
    <div class="chart-wrap"><canvas id="chartLive"></canvas></div>
  </div>

  <div class="seg" data-seg role="tablist" aria-label="Time range">
    <button data-range="3h">3H</button>
    <button data-range="24h" class="active">24H</button>
    <button data-range="7d">7D</button>
    <button data-range="30d">30D</button>
  </div>

  <div class="chart-block">
    <div class="chart-title"><svg class="ic" viewBox="0 0 24 24"><path d="M3 17l5-6 4 3 6-8 3 4"/></svg>Dish Throughput History</div>
    <div class="chart-wrap short"><canvas id="chartDishTp"></canvas></div>
  </div>

  <div class="chart-block grid2">
    <div>
      <div class="chart-title"><svg class="ic" viewBox="0 0 24 24"><path d="M2 12h4l3-8 4 16 3-8h6"/></svg>PoP Latency / Drop Rate</div>
      <div class="chart-wrap short"><canvas id="chartDishLat"></canvas></div>
    </div>
    <div>
      <div class="chart-title"><svg class="ic" viewBox="0 0 24 24"><path d="M12 3a7 7 0 0 1 7 7c0 5-7 11-7 11S5 15 5 10a7 7 0 0 1 7-7z"/><circle cx="12" cy="10" r="2.4"/></svg>Obstruction %</div>
      <div class="chart-wrap short"><canvas id="chartObstr"></canvas></div>
    </div>
  </div>

  <div class="sect"><svg class="ic" viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>Dish Statistics<small id="dishOutage"></small></div>
  <div class="stats" id="dishStats"></div>

  <div id="usageCard2" hidden>
    <div class="sect">
      <svg class="ic" viewBox="0 0 24 24"><path d="M21 8v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8"/><path d="M3 8l9-5 9 5"/><path d="M12 12v5M9.5 14.5L12 17l2.5-2.5"/></svg>
      Data Usage<small id="usageNote">measured from the dish</small>
    </div>
    <div class="stats" id="usageTiles"></div>
    <div class="tlwrap">
      <div class="ubar" id="usageBar" hidden><i id="usageFill"></i><u id="usageMark" hidden></u></div>
      <div class="unote" id="usageSub"></div>
    </div>
    <div class="chart-block">
      <div class="chart-title"><svg class="ic" viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>Daily Traffic</div>
      <div class="chart-wrap short"><canvas id="chartUsage"></canvas></div>
    </div>
  </div>
</section>

<!-- ============ SATS ============ -->
<section class="view" id="view-sats">
  <div class="sect" style="margin-top:0">
    <svg class="ic" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3.6 9h16.8M3.6 15h16.8M12 3a17 17 0 0 1 0 18M12 3a17 17 0 0 0 0 18"/></svg>
    Live Map<small><span class="sdot blink" style="display:inline-block;vertical-align:-1px;margin-right:6px"></span><span id="geoNote">Loading orbital data…</span></small>
  </div>
  <div class="geowrap">
    <canvas id="geoMap" width="1600" height="1000"></canvas>
    <div class="skycap" id="geoCap">Hover a satellite for details</div>
  </div>

  <div class="sect">
    <svg class="ic" viewBox="0 0 24 24"><rect x="9" y="9" width="6" height="6" transform="rotate(45 12 12)"/><path d="M3.5 8.5l4 4M16.5 11.5l4 4M8.5 3.5l4 4M11.5 16.5l4 4"/></svg>
    Sky View<small id="skyNote">Az/El polar plot · N up</small>
  </div>
  <div class="skywrap">
    <canvas id="skyMap" width="1120" height="1120"></canvas>
    <div class="skycap" id="skyCap">Hover a satellite for details</div>
  </div>

  <div class="sect">
    <svg class="ic" viewBox="0 0 24 24"><rect x="9" y="9" width="6" height="6" transform="rotate(45 12 12)"/><path d="M3.5 8.5l4 4M16.5 11.5l4 4M8.5 3.5l4 4M11.5 16.5l4 4"/></svg>
    Satellites Overhead<small id="satNote">TLE-inferred · updated 1 min</small>
  </div>
  <div class="usage-summary" id="satSummary"></div>
  <div class="tblwrap">
    <table>
      <thead><tr>
        <th>Satellite</th>
        <th class="r">NORAD</th>
        <th class="r">Minutes Best</th>
        <th class="r">Min Sep</th>
        <th class="r">Max El</th>
        <th class="r">Min Range</th>
        <th>First Seen</th>
        <th>Last Seen</th>
      </tr></thead>
      <tbody id="satBody"></tbody>
    </table>
  </div>
  <div class="tnote">Serving candidate inferred from public TLEs vs dish boresight — the dish switches satellites every ~15s, this is the per-minute dominant candidate</div>
</section>

<!-- ============ ENERGY ============ -->
<section class="view" id="view-energy">
  <div id="enOff" class="empty" hidden>
    This dish does not report input power on its firmware
  </div>
  <div id="enOn" hidden>
    <div class="hero enhero">
      <div class="metric">
        <div class="label"><svg class="ic" viewBox="0 0 24 24"><path d="M13 2L4 14h6l-1 8 9-12h-6l1-8z"/></svg>Drawing Now</div>
        <div class="num"><span id="enNow">–</span><span class="unit">W</span></div>
        <div class="sub" id="enNowSub">&nbsp;</div>
        <div class="watt"><i id="enBar"></i><s id="enIdleMark" hidden></s></div>
        <div class="heatpill off" id="enHeat">
          <svg class="ic" viewBox="0 0 24 24" style="width:11px;height:11px"><path d="M12 3c2 4-2 5 0 9 3-2 4-6 2-9z"/><path d="M5 14a7 7 0 0 0 14 0"/></svg>
          Snow melt active
        </div>
      </div>
      <div class="metric">
        <div class="label"><svg class="ic" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>Today</div>
        <div class="num"><span id="enToday">–</span><span class="unit">kWh</span></div>
        <div class="sub" id="enTodaySub">&nbsp;</div>
      </div>
      <div class="metric">
        <div class="label"><svg class="ic" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="1"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>This Month</div>
        <div class="num"><span id="enMonth">–</span><span class="unit">kWh</span></div>
        <div class="sub" id="enMonthSub">&nbsp;</div>
      </div>
    </div>

    <div class="enote" id="enSplit"></div>

    <div class="seg" data-seg role="tablist" aria-label="Time range">
      <button data-range="3h">3H</button>
      <button data-range="24h" class="active">24H</button>
      <button data-range="7d">7D</button>
      <button data-range="30d">30D</button>
    </div>

    <div class="chart-block">
      <div class="chart-title"><svg class="ic" viewBox="0 0 24 24"><path d="M3 17l5-6 4 3 6-8 3 4"/></svg>Power Draw
        <span class="livebadge"><span id="enHeatNote"></span></span>
      </div>
      <div class="chart-wrap"><canvas id="chartPower"></canvas></div>
    </div>

    <div class="sect"><svg class="ic" viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>Daily Consumption</div>
    <div class="chart-block"><div class="chart-wrap short"><canvas id="chartDayWh"></canvas></div></div>

    <div class="sect"><svg class="ic" viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>Statistics<small id="enStatNote"></small></div>
    <div class="stats" id="enStats"></div>

    <div id="enCostCard" hidden>
      <div class="sect"><svg class="ic" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M15 9.5a3 3 0 0 0-3-1.5c-1.7 0-3 .9-3 2s1.3 2 3 2 3 .9 3 2-1.3 2-3 2a3 3 0 0 1-3-1.5"/><path d="M12 6v12"/></svg>Running Cost<small id="enPriceNote"></small></div>
      <div class="costgrid" id="enCost"></div>
    </div>
  </div>
</section>

<!-- ============ TESTS ============ -->
<section class="view" id="view-tests">
  <div class="seg" data-seg role="tablist" aria-label="Time range">
    <button data-range="3h">3H</button>
    <button data-range="24h" class="active">24H</button>
    <button data-range="7d">7D</button>
    <button data-range="30d">30D</button>
  </div>

  <div class="sect" style="margin-top:26px">
    <svg class="ic" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
    Test History<small id="testCount"></small>
  </div>
  <div class="tblwrap">
    <table>
      <thead><tr>
        <th>Time</th>
        <th class="r">↓ Mbps</th>
        <th class="r">↑ Mbps</th>
        <th class="r">Latency</th>
        <th class="r">Jitter</th>
        <th class="r">Loaded ↓/↑</th>
        <th class="r">Ping</th>
        <th class="r">Loss</th>
        <th class="r">Data ↓</th>
        <th class="r">Data ↑</th>
        <th class="r">Data Total</th>
        <th>Colo</th>
        <th>Status</th>
      </tr></thead>
      <tbody id="testsBody"></tbody>
    </table>
  </div>
  <div class="tnote">Showing newest first · max 500 rows per range</div>
</section>

<!-- ============ LOG ============ -->
<section class="view" id="view-log">
  <div class="sect" style="margin-top:0">
    <svg class="ic" viewBox="0 0 24 24"><path d="M3 12h4l3-7 4 14 3-7h4"/></svg>
    Outage Timeline<small id="tlNote"></small>
  </div>
  <div class="stats" id="tlStats"></div>
  <div class="causebar" id="tlCauses" hidden></div>
  <div class="tlwrap">
    <canvas id="tlCanvas" width="2000" height="116"></canvas>
    <div class="tlaxis"><span id="tlFrom"></span><span id="tlTo"></span></div>
    <div class="tlkey">
      <span><i style="background:#2a2a2a"></i>Link up</span>
      <span><i style="background:var(--red)"></i>Link outage</span>
      <span><i style="background:var(--warn)"></i>Degraded</span>
      <span><i style="background:#4a4f57"></i>Dish unreachable — not observed</span>
    </div>
    <div class="tlcap" id="tlCap">Hover the timeline for detail</div>
  </div>
  <div class="tblwrap" style="margin-top:8px">
    <table style="min-width:640px">
      <thead><tr>
        <th>Started</th><th>Type</th><th class="r">Duration</th>
        <th class="r">Worst Drop</th><th>Detail</th>
      </tr></thead>
      <tbody id="tlBody"></tbody>
    </table>
  </div>

  <div class="sect">
    <svg class="ic" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3.6 9h16.8M3.6 15h16.8M12 3a17 17 0 0 1 0 18M12 3a17 17 0 0 0 0 18"/></svg>
    WAN IP History<small>All time</small>
  </div>
  <div class="tblwrap">
    <table>
      <thead><tr>
        <th>IP Address</th>
        <th>Hostname (PTR)</th>
        <th>First Seen</th>
        <th>Last Seen</th>
        <th class="r">Tests</th>
      </tr></thead>
      <tbody id="ipBody"></tbody>
    </table>
  </div>
  <div class="tnote">Hostnames resolved via reverse DNS, cached server-side</div>
</section>

<!-- ============ DEBUG ============ -->
<section class="view" id="view-debug">
  <div class="lock" id="dbgLock">
    <svg class="lk-scene" viewBox="0 0 280 150" aria-hidden="true">
      <circle class="st"    cx="30"  cy="26" r="1"/>
      <circle class="st s2" cx="72"  cy="14" r="1"/>
      <circle class="st s3" cx="126" cy="30" r="1.2"/>
      <circle class="st s4" cx="182" cy="12" r="1"/>
      <circle class="st s5" cx="228" cy="28" r="1.2"/>
      <circle class="st s6" cx="256" cy="52" r="1"/>
      <g class="sweep"><line x1="140" y1="126" x2="140" y2="30"/></g>
      <circle class="ring"    cx="140" cy="123" r="7"/>
      <circle class="ring r2" cx="140" cy="123" r="7"/>
      <g class="sat">
        <animateMotion dur="10s" repeatCount="indefinite" path="M-24 46 Q140 4 304 42"/>
        <rect class="pn" x="-9" y="-1.4" width="5" height="2.8"/>
        <rect class="pn" x="4"  y="-1.4" width="5" height="2.8"/>
        <rect x="-3" y="-2.2" width="6" height="4.4"/>
      </g>
      <g class="sat">
        <animateMotion dur="15s" repeatCount="indefinite" path="M304 68 Q140 26 -24 64"/>
        <rect class="pn" x="-9" y="-1.4" width="5" height="2.8"/>
        <rect class="pn" x="4"  y="-1.4" width="5" height="2.8"/>
        <rect x="-3" y="-2.2" width="6" height="4.4"/>
      </g>
      <g class="sat">
        <animateMotion dur="20s" repeatCount="indefinite" path="M-24 22 Q140 -10 304 26"/>
        <rect class="pn" x="-9" y="-1.4" width="5" height="2.8"/>
        <rect class="pn" x="4"  y="-1.4" width="5" height="2.8"/>
        <rect x="-3" y="-2.2" width="6" height="4.4"/>
      </g>
      <line class="ground" x1="0" y1="132" x2="280" y2="132"/>
      <path class="dishb" d="M131 120 Q140 131 149 121"/>
      <path class="dishb" d="M140 126 v6"/>
    </svg>
    <div class="lk-t" id="dbgLockT">Restricted — enter password</div>
    <input type="password" id="dbgPass" placeholder="Password" autocomplete="off">
    <button class="btn" id="dbgUnlock">Unlock</button>
    <div class="lk-err" id="dbgErr"></div>
  </div>

  <div id="dbgTools" hidden>
    <div class="sect" style="margin-top:0">
      <svg class="ic" viewBox="0 0 24 24"><path d="M4 5h16v12H4z"/><path d="M7 9l3 2.5L7 14M12 14h5"/></svg>
      Network Tools<small><button class="btn sec" id="dbgLogout" style="padding:4px 12px">Lock</button></small>
    </div>
    <div class="toolbar">
      <input type="text" id="dbgTarget" placeholder="Host / IP / URL" value="1.1.1.1" autocomplete="off">
      <select id="dbgCount">
        <option value="5">5 probes</option>
        <option value="10" selected>10 probes</option>
        <option value="20">20 probes</option>
      </select>
      <button class="btn" data-tool="ping">Ping</button>
      <button class="btn" data-tool="mtr">MTR</button>
      <button class="btn" data-tool="dns">DNS</button>
      <button class="btn" data-tool="http">HTTP</button>
    </div>
    <div class="toolstatus" id="dbgStatus"></div>

    <div id="dbgPingOut" hidden>
      <div class="sect">Ping<small id="pingTitle"></small></div>
      <div class="stats" id="pingStats"></div>
      <div class="chart-block"><div class="chart-wrap short"><canvas id="chartDbgPing"></canvas></div></div>
    </div>

    <div id="dbgMtrOut" hidden>
      <div class="sect">Route<small id="mtrTitle"></small></div>
      <div class="hops" id="mtrHops"></div>
    </div>

    <div id="dbgDnsOut" hidden>
      <div class="sect">DNS<small id="dnsTitle"></small></div>
      <div class="tblwrap"><table style="min-width:520px">
        <thead><tr><th>Type</th><th>Values</th><th class="r">Time</th></tr></thead>
        <tbody id="dnsBody"></tbody>
      </table></div>
    </div>

    <div id="dbgHttpOut" hidden>
      <div class="sect">HTTP Timing<small id="httpTitle"></small></div>
      <div class="stats" id="httpStats"></div>
      <div class="wf" id="httpWf"></div>
    </div>
  </div>
</section>

<!-- ============ SETTINGS ============ -->
<section class="view" id="view-settings">
  <div class="lock" id="setLock">
    <svg class="lk-scene" viewBox="0 0 280 150" aria-hidden="true">
      <circle class="st"    cx="30"  cy="26" r="1"/>
      <circle class="st s2" cx="72"  cy="14" r="1"/>
      <circle class="st s3" cx="126" cy="30" r="1.2"/>
      <circle class="st s4" cx="182" cy="12" r="1"/>
      <circle class="st s5" cx="228" cy="28" r="1.2"/>
      <circle class="st s6" cx="256" cy="52" r="1"/>
      <g class="sweep"><line x1="140" y1="126" x2="140" y2="30"/></g>
      <circle class="ring"    cx="140" cy="123" r="7"/>
      <circle class="ring r2" cx="140" cy="123" r="7"/>
      <g class="sat">
        <animateMotion dur="10s" repeatCount="indefinite" path="M-24 46 Q140 4 304 42"/>
        <rect class="pn" x="-9" y="-1.4" width="5" height="2.8"/>
        <rect class="pn" x="4"  y="-1.4" width="5" height="2.8"/>
        <rect x="-3" y="-2.2" width="6" height="4.4"/>
      </g>
      <g class="sat">
        <animateMotion dur="15s" repeatCount="indefinite" path="M304 68 Q140 26 -24 64"/>
        <rect class="pn" x="-9" y="-1.4" width="5" height="2.8"/>
        <rect class="pn" x="4"  y="-1.4" width="5" height="2.8"/>
        <rect x="-3" y="-2.2" width="6" height="4.4"/>
      </g>
      <g class="sat">
        <animateMotion dur="20s" repeatCount="indefinite" path="M-24 22 Q140 -10 304 26"/>
        <rect class="pn" x="-9" y="-1.4" width="5" height="2.8"/>
        <rect class="pn" x="4"  y="-1.4" width="5" height="2.8"/>
        <rect x="-3" y="-2.2" width="6" height="4.4"/>
      </g>
      <line class="ground" x1="0" y1="132" x2="280" y2="132"/>
      <path class="dishb" d="M131 120 Q140 131 149 121"/>
      <path class="dishb" d="M140 126 v6"/>
    </svg>
    <div class="lk-t" id="setLockT">Restricted — enter password</div>
    <input type="password" id="setPass" placeholder="Password" autocomplete="off">
    <button class="btn" id="setUnlock">Unlock</button>
    <div class="lk-err" id="setErr"></div>
  </div>

  <div id="setTools" hidden>
    <div class="sect" style="margin-top:0">
      <svg class="ic" viewBox="0 0 24 24"><path d="M12 3v12M8 11l4 4 4-4"/><path d="M4 20h16"/></svg>
      Software Update<small id="updChecked"></small>
    </div>
    <div class="updbox">
      <div class="updver" id="updCur">—</div>
      <div class="updrow" style="margin-top:8px">
        <span id="updState">Checking for updates…</span>
      </div>
      <div class="updnotes" id="updNotes" hidden></div>
      <div class="savebar" style="padding-top:18px">
        <button class="btn" id="updInstall" hidden>Install update</button>
        <button class="btn sec" id="updCheck">Check now</button>
        <a class="btn sec" id="updRepo" href="#" target="_blank" rel="noopener"
           style="text-decoration:none;display:inline-block">Repository</a>
      </div>
      <div class="updlog" id="updLog"></div>
    </div>

    <div class="sect">
      <svg class="ic" viewBox="0 0 24 24"><path d="M21 8v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8"/><path d="M3 8l9-5 9 5"/></svg>
      Data Usage<small>measured from the dish, not estimated</small>
    </div>
    <div class="set-grid">
      <div class="k">Billing cycle<small>day of month it restarts</small></div>
      <div class="set-row">
        <input type="number" id="usDay" style="width:90px" min="1" max="28">
        <span class="tl" style="color:var(--text3)">of each month</span>
      </div>
      <div class="k">Monthly cap<small>0 to hide the bar</small></div>
      <div class="set-row">
        <input type="number" id="usCap" style="width:110px" min="0" step="10">
        <span class="tl" style="color:var(--text3)">GB</span>
      </div>
      <div class="k">Electricity price<small>0 hides the cost panel</small></div>
      <div class="set-row">
        <input type="number" id="enPrice" style="width:110px" min="0" step="0.01">
        <input type="text" id="enCur" style="width:90px" placeholder="EUR" maxlength="8">
        <span class="tl" style="color:var(--text3)">per kWh</span>
      </div>
    </div>

    <div class="sect">
      <svg class="ic" viewBox="0 0 24 24"><path d="M2 12h4l3-8 4 16 3-8h6"/></svg>
      ICMP Health Monitor<small>shown on the dashboard</small>
    </div>
    <div class="set-grid">
      <div class="k">Enabled<small>ping probes from this host</small></div>
      <div class="set-row">
        <label class="tgl"><input type="checkbox" id="hzEn"><span class="bx"></span>
          <span class="tl">Monitor reachability</span></label>
      </div>

      <div class="k">Providers<small>click to add or remove</small></div>
      <div>
        <div class="provgrid" id="hzProv"></div>
        <div class="tgtlist" id="hzTargets"></div>
        <div class="set-row" style="margin-top:10px">
          <input type="text" id="hzCustomName" placeholder="Label" style="width:130px">
          <input type="text" id="hzCustomHost" placeholder="IP or hostname" style="width:180px">
          <button class="btn sec" id="hzAdd">Add</button>
        </div>
        <div class="toolstatus" id="hzStatus" style="padding-top:8px"></div>
      </div>

      <div class="k">Thresholds<small>green up to, amber up to</small></div>
      <div class="set-row">
        <input type="number" id="hzGood" style="width:90px" min="1" max="2000">
        <span class="tl" style="color:var(--text3)">ms green</span>
        <input type="number" id="hzWarn" style="width:90px" min="2" max="5000">
        <span class="tl" style="color:var(--text3)">ms amber</span>
      </div>

      <div class="k">Probe<small>interval and packets per run</small></div>
      <div class="set-row">
        <input type="number" id="hzInt" style="width:90px" min="10" max="3600">
        <span class="tl" style="color:var(--text3)">s</span>
        <input type="number" id="hzCnt" style="width:90px" min="1" max="20">
        <span class="tl" style="color:var(--text3)">packets</span>
      </div>
    </div>

    <div class="sect">
      <svg class="ic" viewBox="0 0 24 24"><path d="M21.6 17.2L13.5 3.9a1.7 1.7 0 0 0-3 0L2.4 17.2"/><path d="M8.5 13l2.5 2.5 5-6"/></svg>
      Telegram Bot
    </div>
    <div class="set-grid">
      <div class="k">Bot token<small>from @BotFather</small></div>
      <div class="set-row">
        <input type="text" id="tgToken" placeholder="123456:ABC-DEF…" style="flex:1;min-width:260px">
        <button class="btn sec" id="tgValidate">Validate</button>
      </div>
      <div class="k">Chat ID<small>where alerts are sent</small></div>
      <div class="set-row">
        <input type="text" id="tgChat" placeholder="e.g. 123456789" style="width:200px">
        <button class="btn sec" id="tgGetChat">Get Chat ID</button>
        <button class="btn sec" id="tgTest">Send Test</button>
      </div>
      <div class="k"></div>
      <div>
        <div class="toolstatus" id="tgStatus"></div>
        <div class="chatpick" id="tgChats"></div>
      </div>
    </div>

    <div class="sect">
      <svg class="ic" viewBox="0 0 24 24"><path d="M12 3l9 16H3z"/><path d="M12 10v4M12 17h.01"/></svg>
      Alerts
    </div>
    <div class="set-grid">
      <div class="k">Speed test</div>
      <div class="set-row">
        <label class="tgl"><input type="checkbox" id="alFail"><span class="bx"></span><span class="tl">Test failed</span></label>
        <label class="tgl"><input type="checkbox" id="alRetry"><span class="bx"></span><span class="tl">Retries</span></label>
        <label class="tgl"><input type="checkbox" id="alLow"><span class="bx"></span><span class="tl">Low download</span></label>
        <input type="number" id="alLowMbps" style="width:84px" min="1" max="500"> <span class="tl" style="color:var(--text3)">Mbps</span>
      </div>
      <div class="k">Dish</div>
      <div class="set-row">
        <label class="tgl"><input type="checkbox" id="alDown"><span class="bx"></span><span class="tl">Unreachable</span></label>
        <label class="tgl"><input type="checkbox" id="alHw"><span class="bx"></span><span class="tl">Hardware alerts</span></label>
        <label class="tgl"><input type="checkbox" id="alDrop"><span class="bx"></span><span class="tl">High drop rate</span></label>
        <input type="number" id="alDropPct" style="width:84px" min="1" max="100"> <span class="tl" style="color:var(--text3)">%</span>
      </div>
      <div class="k">Network</div>
      <div class="set-row">
        <label class="tgl"><input type="checkbox" id="alIp"><span class="bx"></span><span class="tl">New WAN IP</span></label>
      </div>
    </div>

    <div class="sect">
      <svg class="ic" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>
      Intervals<small>applied automatically in background</small>
    </div>
    <div class="set-grid">
      <div class="k">Speed test<small>minutes between runs</small></div>
      <div class="set-row"><input type="number" id="ivSp" style="width:100px" min="2" max="120"> <span class="tl" style="color:var(--text3)">min</span></div>
      <div class="k">Dish telemetry<small>seconds between samples</small></div>
      <div class="set-row"><input type="number" id="ivDish" style="width:100px" min="15" max="3600"> <span class="tl" style="color:var(--text3)">s</span></div>
      <div class="k">Satellite tracker<small>seconds between runs</small></div>
      <div class="set-row"><input type="number" id="ivSats" style="width:100px" min="30" max="3600"> <span class="tl" style="color:var(--text3)">s</span></div>
      <div class="k">Live poll<small>dish UI refresh, seconds</small></div>
      <div class="set-row"><input type="number" id="ivLive" style="width:100px" min="1" max="30"> <span class="tl" style="color:var(--text3)">s</span></div>
      <div class="k">Retry threshold<small>Mbps considered broken</small></div>
      <div class="set-row"><input type="number" id="ivSane" style="width:100px" min="0" max="100"> <span class="tl" style="color:var(--text3)">Mbps</span></div>
      <div class="k">Max attempts<small>per speed test</small></div>
      <div class="set-row"><input type="number" id="ivAtt" style="width:100px" min="1" max="5"></div>
    </div>

    <div class="sect">
      <svg class="ic" viewBox="0 0 24 24"><path d="M12 3a7 7 0 0 1 7 7c0 5-7 11-7 11S5 15 5 10a7 7 0 0 1 7-7z"/><circle cx="12" cy="10" r="2.4"/></svg>
      Observer Location<small>used by the satellite tracker</small>
    </div>
    <div class="set-grid">
      <div class="k">Latitude<small>decimal degrees · empty = ask the dish</small></div>
      <div class="set-row"><input type="number" id="locLat" style="width:170px" step="0.000001" min="-90" max="90" placeholder="auto"></div>
      <div class="k">Longitude<small>decimal degrees</small></div>
      <div class="set-row"><input type="number" id="locLon" style="width:170px" step="0.000001" min="-180" max="180" placeholder="auto"></div>
      <div class="k">Altitude<small>metres above sea level</small></div>
      <div class="set-row"><input type="number" id="locAlt" style="width:100px" min="0" max="9000"> <span class="tl" style="color:var(--text3)">m</span></div>
    </div>

    <div class="sect">
      <svg class="ic" viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="10" rx="1"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
      Data &amp; Security
    </div>
    <div class="set-grid">
      <div class="k">DB retention<small>days of history to keep · 0 = forever</small></div>
      <div class="set-row"><input type="number" id="retDays" style="width:100px" min="0" max="3650"> <span class="tl" style="color:var(--text3)">days</span></div>
      <div class="k">Admin password<small>min 8 characters</small></div>
      <div class="set-row">
        <input type="password" id="pwNew" placeholder="New password" autocomplete="new-password" style="width:220px">
        <button class="btn sec" id="pwChange">Change</button>
        <span class="toolstatus" id="pwStatus" style="padding:0"></span>
      </div>
    </div>

    <div class="savebar">
      <button class="btn" id="setSave">Save</button>
      <span class="toolstatus" id="setStatus" style="padding:0"></span>
    </div>
  </div>
</section>

</main>

<footer class="foot">
  <span><b>stardashy</b> <span id="footVer">v<?= htmlspecialchars(trim(@file_get_contents(dirname(__DIR__) . '/VERSION')) ?: '?') ?></span></span>
  <span>·</span>
  <span>Open source · MIT</span>
  <span>·</span>
  <span>Unofficial — not affiliated with SpaceX / Starlink</span>
</footer>

<script>
const $ = id => document.getElementById(id);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let range = '24h';
let activeView = 'dash';
const charts = {};

const css = v => getComputedStyle(document.documentElement).getPropertyValue(v).trim();
const FONT = "'D-DIN',Arial,sans-serif";

function fmtTs(ts){
  const d = new Date(ts * 1000);
  const opts = (range === '7d' || range === '30d')
    ? {month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'}
    : {hour:'2-digit', minute:'2-digit'};
  return d.toLocaleString([], opts);
}
function fmtFull(ts){
  return new Date(ts * 1000).toLocaleString([],
    {year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'});
}
function fmtUptime(s){
  if (s == null) return '–';
  const d = Math.floor(s / 86400), h = Math.floor((s % 86400) / 3600),
        m = Math.floor((s % 3600) / 60);
  return (d ? d + 'd ' : '') + h + 'h ' + m + 'm';
}

/* ISO country code -> emoji flag, via regional indicator symbols. */
function flagOf(cc){
  if (!/^[A-Za-z]{2}$/.test(cc || '')) return '';
  return String.fromCodePoint(...cc.toUpperCase().split('')
    .map(c => 0x1F1E6 + c.charCodeAt(0) - 65));
}

function kmBetween(la1, lo1, la2, lo2){
  const R = 6371, r = Math.PI / 180;
  const a = Math.sin((la2 - la1) * r / 2) ** 2 +
    Math.cos(la1 * r) * Math.cos(la2 * r) * Math.sin((lo2 - lo1) * r / 2) ** 2;
  return 2 * R * Math.asin(Math.sqrt(a));
}

/* "3 min ago" beats a bare clock time when you are checking whether the
   collector is still alive. Recomputed on a ticker, not only on data load. */
function agoText(ts){
  const s = Math.max(0, Math.floor(Date.now() / 1000 - ts));
  if (s < 60)    return 'just now';
  if (s < 3600)  return Math.floor(s / 60) + ' min ago';
  if (s < 86400) return Math.floor(s / 3600) + ' h ago';
  return Math.floor(s / 86400) + ' d ago';
}

/* Change versus the previous successful test. Higher is better for
   throughput, worse for latency, so callers pass which way is good. */
function deltaHtml(cur, prev, higherIsBetter, unit){
  if (cur == null || prev == null || !isFinite(cur) || !isFinite(prev)) return '';
  const d = cur - prev;
  const pct = prev !== 0 ? Math.abs(d / prev) * 100 : 0;
  if (Math.abs(d) < 0.05 || pct < 1.5) return '<span class="dlt flat">=</span>';
  const good = higherIsBetter ? d > 0 : d < 0;
  const arrow = d > 0 ? '\u25b2' : '\u25bc';
  const val = Math.abs(d) >= 10 ? Math.round(Math.abs(d)) : Math.abs(d).toFixed(1);
  return `<span class="dlt ${good ? 'up' : 'dn'}">${arrow} ${val}${unit}</span>`;
}

function bbGrade(ms){
  if (ms == null) return null;
  if (ms < 5)   return ['A+', false];
  if (ms < 30)  return ['A', false];
  if (ms < 60)  return ['B', false];
  if (ms < 200) return ['C', false];
  if (ms < 400) return ['D', true];
  return ['F', true];
}

function faintFill(ctx){
  const g = ctx.createLinearGradient(0, 0, 0, ctx.canvas.height);
  g.addColorStop(0, 'rgba(255,255,255,.12)');
  g.addColorStop(1, 'rgba(255,255,255,0)');
  return g;
}

const baseOpts = () => ({
  responsive:true, maintainAspectRatio:false, animation:{duration:300},
  interaction:{mode:'index', intersect:false}, spanGaps:true,
  plugins:{
    legend:{labels:{usePointStyle:true, pointStyleWidth:7, boxHeight:7,
      font:{size:10, family:FONT}, color:css('--text3')}},
    tooltip:{
      backgroundColor:'#0a0a0a', titleColor:'#fff', bodyColor:'#c8ccd2',
      borderColor:css('--line'), borderWidth:1, cornerRadius:2, padding:12,
      titleFont:{weight:'600', family:FONT}, bodyFont:{family:FONT},
      boxPadding:4, displayColors:false
    }
  },
  scales:{
    x:{grid:{display:false}, border:{color:css('--line')},
       ticks:{color:css('--text3'), maxTicksLimit:8, font:{size:10, family:FONT}}},
    y:{grid:{color:css('--line-soft')}, border:{display:false},
       ticks:{color:css('--text3'), font:{size:10, family:FONT}}, beginAtZero:true}
  }
});

function line(label, data, color, opts={}){
  return Object.assign({
    label, data, borderColor:color, backgroundColor:'transparent',
    fill:false, tension:0, pointRadius:0, pointHitRadius:12, borderWidth:1.5,
  }, opts);
}

function mk(id, cfg){
  charts[id]?.destroy();
  charts[id] = new Chart($(id).getContext('2d'), cfg);
}

function fmtMB(mb){
  if (mb == null) return '–';
  return mb >= 1000 ? (mb/1000).toFixed(2) + ' <small>GB</small>' : mb + ' <small>MB</small>';
}

/* ================= main render (history data) ================= */
function render(data){
  const rows = data.rows || [];
  const latest = data.latest, stats = data.stats, usage = data.usage;

  if (latest){
    $('downNow').textContent = Math.round(latest.down_mbps);
    $('upNow').textContent = Math.round(latest.up_mbps);
    $('latNow').textContent = latest.latency_ms ?? '–';
    const prev = rows.length > 1 ? rows[rows.length - 2] : null;
    $('downSub').innerHTML = (stats ? `Avg <b>${stats.down_avg}</b> · Peak <b>${stats.down_max}</b>` : '')
      + deltaHtml(latest.down_mbps, prev?.down_mbps, true, '');
    $('upSub').innerHTML = (stats ? `Avg <b>${stats.up_avg}</b> · Peak <b>${stats.up_max}</b>` : '')
      + deltaHtml(latest.up_mbps, prev?.up_mbps, true, '');
    const parts = [];
    if (latest.jitter_ms != null) parts.push(`Jitter <b>${latest.jitter_ms}</b>`);
    if (latest.lat_down_ms != null) parts.push(`Loaded <b>${latest.lat_down_ms}/${latest.lat_up_ms ?? '–'}</b>`);
    $('latSub').innerHTML = (parts.join(' · ') || '&nbsp;')
      + deltaHtml(latest.latency_ms, prev?.latency_ms, false, ' ms');

    const g = bbGrade(stats?.bufferbloat_ms);
    if (g){
      $('gradeWrap').hidden = false;
      $('gradePill').textContent = g[0];
      $('gradePill').className = 'pill' + (g[1] ? ' bad' : '');
      $('gradeText').textContent = `Bufferbloat +${stats.bufferbloat_ms} ms`;
    } else {
      $('gradeWrap').hidden = true;
    }

    renderPop(latest, data.cfg);
    LAST_TS = latest.ts;
    tickAgo();
    $('dot').classList.remove('err');

    const ic = {
      ip:'<svg class="ic" viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="10" rx="1"/><path d="M7 17v2M17 17v2"/></svg>',
      ping:'<svg class="ic" viewBox="0 0 24 24"><path d="M2 12h4l3-8 4 16 3-8h6"/></svg>',
      loss:'<svg class="ic" viewBox="0 0 24 24"><path d="M12 3l9 16H3z"/><path d="M12 10v4M12 17h.01"/></svg>',
      up:'<svg class="ic" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/></svg>',
    };
    const m = [];
    if (latest.client_ip) m.push(`${ic.ip}IP <b>${latest.client_ip}</b>`);
    if (latest.ping_ms != null) m.push(`${ic.ping}Ping <b>${latest.ping_ms} ms</b>`);
    if (latest.ping_loss != null) m.push(`${ic.loss}Loss <b>${latest.ping_loss}%</b>`);
    if (stats?.success_pct != null) m.push(`${ic.up}Uptime <b>${stats.success_pct}%</b>`);
    $('metaLine').innerHTML = m.map(s => `<span>${s}</span>`).join('');
    setStatus(
      latest.error ? 'bad' : ((latest.ping_loss ?? 0) > 0 ? 'warn' : 'ok'),
      `${Math.round(latest.down_mbps)}\u2193 ${Math.round(latest.up_mbps)}\u2191 \u00b7 Stardashy`);
  } else {
    $('lastRun').textContent = 'No data';
    setStatus('warn', 'Stardashy');
  }

  if (usage && usage.last5?.length){
    $('usageCard').hidden = false;
    $('usageTests').innerHTML = usage.last5.map(t => `
      <div class="utile">
        <div class="t">${new Date(t.ts * 1000).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</div>
        <div class="mb">${fmtMB(t.total_mb)}</div>
        <div class="split">↓ ${t.down_mb} · ↑ ${t.up_mb} MB</div>
      </div>`).join('');
    $('usageSummary').innerHTML = `
      <div class="usum"><div class="k">Last 5 Tests</div>
        <div class="v">${fmtMB(usage.last5_total_mb)}</div></div>
      <div class="usum"><div class="k">Total ${data.range}</div>
        <div class="v">${usage.range_total_gb}<small>GB</small></div></div>
      <div class="usum"><div class="k">Avg / Test</div>
        <div class="v">${fmtMB(usage.avg_per_test_mb)}</div></div>
      <div class="usum"><div class="k">Est. Daily</div>
        <div class="v">${usage.est_daily_gb ?? '–'}<small>GB</small></div></div>`;
  } else {
    $('usageCard').hidden = true;
  }

  if (stats){
    const lossBad = stats.loss_max > 0;
    $('statsGrid').innerHTML = `
      <div class="stat"><div class="k">Tests Run</div><div class="v">${stats.tests}</div></div>
      <div class="stat"><div class="k">Success</div>
        <div class="v ${stats.success_pct < 95 ? 'bad' : ''}">${stats.success_pct}<small>%</small></div></div>
      <div class="stat"><div class="k" title="90% of tests were faster than this">Download P10</div>
        <div class="v">${stats.down_p10 ?? '–'}<small>Mbps</small></div></div>
      <div class="stat"><div class="k">Min Download</div>
        <div class="v">${stats.down_min}<small>Mbps</small></div></div>
      <div class="stat"><div class="k">Avg Latency</div>
        <div class="v">${stats.lat_avg ?? '–'}<small>ms</small></div></div>
      <div class="stat"><div class="k">Avg Jitter</div>
        <div class="v">${stats.jit_avg ?? '–'}<small>ms</small></div></div>
      <div class="stat"><div class="k">Avg Ping</div>
        <div class="v">${stats.ping_avg ?? '–'}<small>ms</small></div></div>
      <div class="stat"><div class="k">Max Loss</div>
        <div class="v ${lossBad ? 'bad' : ''}">${stats.loss_max ?? '–'}<small>%</small></div></div>`;
  } else {
    $('statsGrid').innerHTML = '';
  }

  const labels = rows.map(r => fmtTs(r.ts));
  const white = '#ffffff', dim = css('--dim'), red = css('--red');

  const c1 = $('chartTp').getContext('2d');
  mk('chartTp', {type:'line', data:{labels, datasets:[
    line('Download', rows.map(r => r.down_mbps), white,
      {fill:true, backgroundColor:faintFill(c1)}),
    line('Upload', rows.map(r => r.up_mbps), dim),
  ]}, options:baseOpts()});

  mk('chartLat', {type:'line', data:{labels, datasets:[
    line('Idle', rows.map(r => r.latency_ms), white),
    line('Loaded ↓', rows.map(r => r.lat_down_ms), dim, {borderDash:[4,4]}),
    line('Loaded ↑', rows.map(r => r.lat_up_ms), css('--text3'), {borderDash:[2,3]}),
  ]}, options:baseOpts()});

  const pOpts = baseOpts();
  pOpts.scales.y1 = {position:'right', grid:{display:false}, border:{display:false},
    ticks:{color:css('--text3'), font:{size:10, family:FONT}, callback:v => v + '%'},
    beginAtZero:true, suggestedMax:5};
  mk('chartPing', {data:{labels, datasets:[
    Object.assign(line('Ping (ms)', rows.map(r => r.ping_ms), white), {type:'line'}),
    {type:'bar', label:'Loss (%)', data:rows.map(r => r.ping_loss),
     backgroundColor:red, borderRadius:0, yAxisID:'y1',
     barThickness:'flex', maxBarThickness:6},
  ]}, options:pOpts});

  /* tests table */
  const tests = data.tests || [];
  $('testCount').textContent = tests.length + ' tests';
  $('testsBody').innerHTML = tests.map(t => {
    const fail = t.error != null;
    const time = `<td>${fmtFull(t.ts)}</td>`;
    if (fail){
      return `<tr>${time}
        <td class="r dim" colspan="10"><span class="mut">${esc(t.error)}</span></td>
        <td class="dim">${t.colo ?? '–'}</td>
        <td><span class="st-fail">FAIL</span></td></tr>`;
    }
    return `<tr>${time}
      <td class="r">${t.down ?? '–'}</td>
      <td class="r">${t.up ?? '–'}</td>
      <td class="r">${t.lat ?? '–'} <span class="mut">ms</span></td>
      <td class="r">${t.jit ?? '–'}</td>
      <td class="r dim">${t.lat_d ?? '–'}/${t.lat_u ?? '–'}</td>
      <td class="r">${t.ping ?? '–'} <span class="mut">ms</span></td>
      <td class="r ${t.loss > 0 ? '' : 'dim'}">${t.loss ?? '–'}%</td>
      <td class="r dim">${t.mb_down ?? '–'}</td>
      <td class="r dim">${t.mb_up ?? '–'}</td>
      <td class="r">${t.mb_total != null ? t.mb_total + ' <span class="mut">MB</span>' : '–'}</td>
      <td class="dim">${t.colo ?? '–'}</td>
      <td><span class="st-ok">OK</span>${t.retries > 0
        ? `<span class="st-fail" style="margin-left:6px" title="Result below 5 Mbps sanity threshold — test was repeated">RETRY \u00d7${t.retries}</span>`
        : ''}</td></tr>`;
  }).join('') || `<tr><td colspan="13" class="empty">No tests in range</td></tr>`;

  /* IP log */
  const ips = data.ip_history || [];
  $('ipBody').innerHTML = ips.map(i => `
    <tr>
      <td>${i.ip}${i.current ? '<span class="ip-current">CURRENT</span>' : ''}</td>
      <td class="dim">${i.hostname ? esc(i.hostname) : '<span class="mut">no PTR</span>'}</td>
      <td class="dim">${fmtFull(i.first_seen)}</td>
      <td class="dim">${fmtFull(i.last_seen)}</td>
      <td class="r">${i.tests}</td>
    </tr>`).join('') || `<tr><td colspan="5" class="empty">No IP data</td></tr>`;

  /* dish history */
  renderDishHistory(data.dish);

  /* satellites */
  renderSats(data.sats);
}

function renderSats(sats){
  if (!sats){
    $('satSummary').innerHTML = '';
    $('satBody').innerHTML =
      `<tr><td colspan="8" class="empty">Satellite tracker not running</td></tr>`;
    return;
  }
  const l = sats.latest;
  let top3 = [];
  try { top3 = l?.top3 ? JSON.parse(l.top3) : []; } catch(e){}
  $('satSummary').innerHTML = `
    <div class="usum"><div class="k">Serving Candidate</div>
      <div class="v" style="font-size:17px;margin-top:10px">${l?.best_name ?? '–'}</div></div>
    <div class="usum"><div class="k">Separation / Elevation</div>
      <div class="v">${l?.best_sep_deg ?? '–'}<small>°</small> / ${l?.best_el ?? '–'}<small>°</small></div></div>
    <div class="usum"><div class="k">Visible Now (&ge;25°)</div>
      <div class="v">${l?.visible_count ?? '–'}<small>avg ${sats.avg_visible ?? '–'}</small></div></div>
    <div class="usum"><div class="k">Distinct in Range</div>
      <div class="v">${sats.distinct}</div></div>`;
  $('satNote').textContent = top3.length > 1
    ? 'Runners-up: ' + top3.slice(1).map(t => `${esc(t.name)} (${t.sep}°)`).join(' · ')
    : 'TLE-inferred · updated 1 min';

  const seen = sats.seen || [];
  $('satBody').innerHTML = seen.map(s => `
    <tr>
      <td>${esc(s.name)}</td>
      <td class="r dim">${s.norad ?? '–'}</td>
      <td class="r">${s.minutes}</td>
      <td class="r dim">${s.min_sep ?? '–'}°</td>
      <td class="r dim">${s.max_el ?? '–'}°</td>
      <td class="r dim">${s.min_range ?? '–'} <span class="mut">km</span></td>
      <td class="dim">${fmtFull(s.first_seen)}</td>
      <td class="dim">${fmtFull(s.last_seen)}</td>
    </tr>`).join('') ||
    `<tr><td colspan="8" class="empty">No satellite data yet</td></tr>`;
}

/* ================= dish history charts ================= */
/* The dish tells us when it is rate limited and why, so there is no need to
   infer throttling from a speed graph. NO_LIMIT is the normal state. */
/* Only these four mean the dish is genuinely restricting bandwidth. Matching
   a known list, rather than "anything that is not NO_LIMIT", keeps the badge
   fail-safe: a missing field, an unexpected string, or an enum arriving as a
   number all leave it hidden. A warning that fires without cause is worse
   than no warning, and this one sits in the header where it cannot be
   ignored. */
const THROTTLE_REASONS = {
  POLICY_LIMIT: 'Policy limit',
  USER_CUSTOM_LIMIT: 'Custom limit',
  OVERAGE_LIMIT: 'Overage limit',
  LOW_SPEED_POLICY_LIMIT: 'Low-speed policy',
};

function renderThrottle(latest){
  const badge = $('thrBadge');
  if (!badge) return;
  const named = v => (typeof v === 'string'
    && THROTTLE_REASONS[v.trim().toUpperCase()]) || null;
  const dl = named(latest?.dl_limit), ul = named(latest?.ul_limit);
  if (!dl && !ul){ badge.classList.remove('on'); return; }
  const parts = [];
  if (dl) parts.push('\u2193 ' + dl);
  if (ul) parts.push('\u2191 ' + ul);
  $('thrText').textContent = parts.join(' \u00b7 ');
  badge.title = 'The dish reports it is being rate limited: ' + parts.join(', ');
  badge.classList.add('on');
}

function renderDishHistory(dish){
  const dr = dish?.rows || [];
  const labels = dr.map(r => fmtTs(r.ts));
  const white = '#ffffff', dim = css('--dim'), red = css('--red');

  const cd = $('chartDishTp').getContext('2d');
  mk('chartDishTp', {type:'line', data:{labels, datasets:[
    line('Downlink', dr.map(r => r.down_mbps), white,
      {fill:true, backgroundColor:faintFill(cd)}),
    line('Uplink', dr.map(r => r.up_mbps), dim),
  ]}, options:baseOpts()});

  const lOpts = baseOpts();
  lOpts.scales.y1 = {position:'right', grid:{display:false}, border:{display:false},
    ticks:{color:css('--text3'), font:{size:10, family:FONT}, callback:v => v + '%'},
    beginAtZero:true, suggestedMax:5};
  mk('chartDishLat', {data:{labels, datasets:[
    Object.assign(line('PoP latency (ms)', dr.map(r => r.pop_ms), white), {type:'line'}),
    {type:'bar', label:'Drop rate (%)', data:dr.map(r => r.drop_pct),
     backgroundColor:red, borderRadius:0, yAxisID:'y1',
     barThickness:'flex', maxBarThickness:6},
  ]}, options:lOpts});

  mk('chartObstr', {type:'line', data:{labels, datasets:[
    line('Obstruction (%)', dr.map(r => r.obstr_pct), white),
  ]}, options:baseOpts()});

  const l = dish?.latest;
  renderThrottle(l);
  if (l){
    let alerts = [];
    try { alerts = l.alerts ? JSON.parse(l.alerts) : []; } catch(e){}
    $('dishOutage').textContent =
      `Outage ${dish.outage_total_s ?? 0}s in range · Bucket ${dish.bucket}s`;
    $('dishStats').innerHTML = `
      <div class="stat"><div class="k">Uptime</div><div class="v">${fmtUptime(l.uptime_s)}</div></div>
      <div class="stat"><div class="k">GPS Sats</div><div class="v">${l.gps_sats ?? '–'}</div></div>
      <div class="stat"><div class="k">Ethernet</div><div class="v">${l.eth_mbps ?? '–'}<small>Mbps</small></div></div>
      <div class="stat"><div class="k">Alerts</div>
        <div class="v ${alerts.length ? 'bad' : ''}">${alerts.length}</div></div>
      <div class="stat"><div class="k">Tilt</div><div class="v">${l.tilt ?? '–'}<small>°</small></div></div>
      <div class="stat"><div class="k">Azimuth</div><div class="v">${l.azim ?? '–'}<small>°</small></div></div>
      <div class="stat"><div class="k">Elevation</div><div class="v">${l.elev ?? '–'}<small>°</small></div></div>
      <div class="stat"><div class="k">Firmware</div>
        <div class="v" style="font-size:13px;margin-top:12px">${l.sw ?? '–'}</div></div>`;
  }
}

/* ================= sky map ================= */
const SKY = {size:560, pad:34, elMask:25, data:null, pts:[]};

function skyXY(az, el){
  const R = (SKY.size / 2) - SKY.pad;
  const r = R * (90 - el) / (90 - SKY.elMask);
  const a = (az - 90) * Math.PI / 180;   // 0° = N (up)
  return [SKY.size / 2 + r * Math.cos(a), SKY.size / 2 + r * Math.sin(a)];
}

function drawSky(){
  const d = SKY.data;
  const cv = $('skyMap'), ctx = cv.getContext('2d');
  ctx.setTransform(2, 0, 0, 2, 0, 0);   // 1120 backing -> 560 logical
  ctx.clearRect(0, 0, SKY.size, SKY.size);
  ctx.font = "10px 'D-DIN',Arial,sans-serif";
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';

  const C = SKY.size / 2, R = C - SKY.pad;

  /* elevation rings */
  [SKY.elMask, 40, 60, 80].forEach(el => {
    const r = R * (90 - el) / (90 - SKY.elMask);
    ctx.beginPath();
    ctx.arc(C, C, r, 0, Math.PI * 2);
    ctx.strokeStyle = 'rgba(255,255,255,.10)';
    ctx.lineWidth = 1;
    ctx.stroke();
    ctx.fillStyle = '#565b63';
    ctx.fillText(el + '\u00b0', C, C - r + 9);
  });
  /* cross hairs */
  ctx.strokeStyle = 'rgba(255,255,255,.06)';
  ctx.beginPath();
  ctx.moveTo(C - R, C); ctx.lineTo(C + R, C);
  ctx.moveTo(C, C - R); ctx.lineTo(C, C + R);
  ctx.stroke();
  /* cardinals */
  ctx.fillStyle = '#8a8f98';
  ctx.fillText('N', C, C - R - 14);
  ctx.fillText('S', C, C + R + 14);
  ctx.fillText('E', C + R + 14, C);
  ctx.fillText('W', C - R - 14, C);

  SKY.pts = [];
  if (!d) return;
  SKY.elMask = d.el_mask || 25;

  /* boresight marker */
  if (d.bs_az != null){
    const [bx, by] = skyXY(d.bs_az, d.bs_el);
    ctx.strokeStyle = '#8a8f98';
    ctx.lineWidth = 1;
    ctx.strokeRect(bx - 6, by - 6, 12, 12);
    ctx.beginPath();
    ctx.moveTo(bx - 9, by); ctx.lineTo(bx - 6, by);
    ctx.moveTo(bx + 6, by); ctx.lineTo(bx + 9, by);
    ctx.moveTo(bx, by - 9); ctx.lineTo(bx, by - 6);
    ctx.moveTo(bx, by + 6); ctx.lineTo(bx, by + 9);
    ctx.stroke();
  }

  /* satellites */
  (d.sats || []).forEach(s => {
    const [x, y] = skyXY(s.az, s.el);
    const best = s.norad === d.best_norad;
    ctx.beginPath();
    ctx.arc(x, y, best ? 5 : 2.6, 0, Math.PI * 2);
    ctx.fillStyle = best ? '#ffffff' : '#8a8f98';
    ctx.fill();
    if (best){
      ctx.beginPath();
      ctx.arc(x, y, 9, 0, Math.PI * 2);
      ctx.strokeStyle = '#ffffff';
      ctx.lineWidth = 1;
      ctx.stroke();
      ctx.fillStyle = '#ffffff';
      ctx.textAlign = x > SKY.size - 110 ? 'right' : 'left';
      ctx.fillText(s.name, x + (x > SKY.size - 110 ? -14 : 14), y);
      ctx.textAlign = 'center';
    }
    SKY.pts.push({x, y, s, best});
  });
}

async function loadSky(){
  try{
    const res = await fetch('sky.php', {cache:'no-store'});
    const d = await res.json();
    if (d.error) return;
    SKY.data = d;
    SKY.elMask = d.el_mask || 25;
    $('skyNote').textContent =
      `${(d.sats || []).length} satellites \u2265 ${SKY.elMask}\u00b0 \u00b7 ` +
      new Date(d.ts * 1000).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    drawSky();
  }catch(e){}
}

$('skyMap').addEventListener('mousemove', ev => {
  const rect = ev.target.getBoundingClientRect();
  const scale = SKY.size / rect.width;
  const mx = (ev.clientX - rect.left) * scale;
  const my = (ev.clientY - rect.top) * scale;
  let hit = null, dmin = 12;
  for (const p of SKY.pts){
    const dd = Math.hypot(p.x - mx, p.y - my);
    if (dd < dmin){ dmin = dd; hit = p; }
  }
  $('skyCap').innerHTML = hit
    ? `<b>${esc(hit.s.name)}</b> \u00b7 NORAD ${hit.s.norad} \u00b7 ` +
      `Az ${hit.s.az}\u00b0 El ${hit.s.el}\u00b0 \u00b7 ` +
      `Sep ${hit.s.sep}\u00b0 \u00b7 ${hit.s.rng} km` +
      (hit.best ? ' \u00b7 SERVING CANDIDATE' : '')
    : 'Hover a satellite for details';
});

/* ================= geographic live map ================= */
const GEO = {
  W:800, H:500, lat0:null, lon0:null, HLON:14,
  recs:[], work:[], pts:[], off:null, geo:null,
  inited:false, timer:null, lastScan:0, obsGd:null, hover:null,
};

function geoProj(lat, lon){
  const ppdX = GEO.W / (2 * GEO.HLON);
  const ppdY = ppdX / Math.cos(GEO.lat0 * Math.PI / 180);
  return [GEO.W / 2 + (lon - GEO.lon0) * ppdX,
          GEO.H / 2 - (lat - GEO.lat0) * ppdY];
}
function geoHLat(){
  const ppdX = GEO.W / (2 * GEO.HLON);
  const ppdY = ppdX / Math.cos(GEO.lat0 * Math.PI / 180);
  return GEO.H / 2 / ppdY;
}
function haversineKm(la1, lo1, la2, lo2){
  const R = 6371, r = Math.PI / 180;
  const a = Math.sin((la2 - la1) * r / 2) ** 2 +
    Math.cos(la1 * r) * Math.cos(la2 * r) * Math.sin((lo2 - lo1) * r / 2) ** 2;
  return 2 * R * Math.asin(Math.sqrt(a));
}

function geoOffscreen(){
  const c = document.createElement('canvas');
  c.width = GEO.W; c.height = GEO.H;
  const x = c.getContext('2d');
  x.fillStyle = '#000';
  x.fillRect(0, 0, GEO.W, GEO.H);

  /* graticule 5deg */
  x.strokeStyle = 'rgba(255,255,255,.05)';
  x.lineWidth = 1;
  x.beginPath();
  for (let lon = -180; lon <= 180; lon += 5){
    const [px] = geoProj(GEO.lat0, lon);
    if (px < 0 || px > GEO.W) continue;
    x.moveTo(px, 0); x.lineTo(px, GEO.H);
  }
  for (let lat = -85; lat <= 85; lat += 5){
    const [, py] = geoProj(lat, GEO.lon0);
    if (py < 0 || py > GEO.H) continue;
    x.moveTo(0, py); x.lineTo(GEO.W, py);
  }
  x.stroke();

  /* country borders */
  if (GEO.geo){
    x.strokeStyle = 'rgba(255,255,255,.22)';
    x.lineWidth = 1;
    x.beginPath();
    for (const f of GEO.geo.features){
      const g = f.geometry;
      if (!g) continue;
      const polys = g.type === 'Polygon' ? [g.coordinates]
                  : g.type === 'MultiPolygon' ? g.coordinates : [];
      for (const poly of polys){
        for (const ring of poly){
          let started = false;
          for (const [lon, lat] of ring){
            const [px, py] = geoProj(lat, lon);
            if (px < -60 || px > GEO.W + 60 || py < -60 || py > GEO.H + 60){
              started = false; continue;
            }
            if (!started){ x.moveTo(px, py); started = true; }
            else x.lineTo(px, py);
          }
        }
      }
    }
    x.stroke();
  }

  /* 25-deg elevation ground ring (~938 km for 550 km shells) */
  const ringKm = 938;
  const ppdX = GEO.W / (2 * GEO.HLON);
  const ppdY = ppdX / Math.cos(GEO.lat0 * Math.PI / 180);
  const [qx, qy] = geoProj(GEO.lat0, GEO.lon0);
  x.setLineDash([4, 5]);
  x.strokeStyle = 'rgba(255,255,255,.16)';
  x.beginPath();
  x.ellipse(qx, qy, (ringKm / 111.32) * ppdX / Math.cos(GEO.lat0 * Math.PI / 180),
            (ringKm / 111.32) * ppdY, 0, 0, Math.PI * 2);
  x.stroke();
  x.setLineDash([]);

  GEO.off = c;
}

async function geoInit(){
  if (GEO.inited) return;
  GEO.inited = true;
  try{
    /* observer position comes from sky.json (published by sat_tracker) */
    if (!SKY.data) await loadSky();
    if (SKY.data?.qth){ GEO.lat0 = SKY.data.qth.lat; GEO.lon0 = SKY.data.qth.lon; }
    if (GEO.lat0 == null || GEO.lon0 == null){
      GEO.inited = false;
      $('geoNote').textContent = 'Waiting for observer location — satellite tracker has not published sky.json yet';
      return;
    }
    GEO.obsGd = {
      latitude: GEO.lat0 * Math.PI / 180,
      longitude: GEO.lon0 * Math.PI / 180,
      height: SKY.data?.qth?.alt_km ?? 0,
    };

    $('geoNote').textContent = 'Loading TLE set\u2026';
    const txt = await (await fetch('tles.php', {cache:'no-store'})).text();
    const lines = txt.split('\n').map(l => l.trim()).filter(Boolean);
    for (let i = 0; i + 2 < lines.length + 1; i += 3){
      const name = lines[i], l1 = lines[i + 1], l2 = lines[i + 2];
      if (!l1 || !l2 || l1[0] !== '1' || l2[0] !== '2') continue;
      try{
        const rec = satellite.twoline2satrec(l1, l2);
        GEO.recs.push({name, rec, norad: rec.satnum});
      }catch(e){}
    }

    $('geoNote').textContent = 'Loading map data\u2026';
    let topo;
    try{
      const lr = await fetch('assets/countries-110m.json');
      if (!lr.ok) throw new Error('no local atlas');
      topo = await lr.json();
    }catch(e){
      topo = await (await fetch(
        'https://cdn.jsdelivr.net/npm/world-atlas@2/countries-110m.json')).json();
    }
    GEO.geo = topojson.feature(topo, topo.objects.countries);
    geoOffscreen();

    geoScan();
    $('geoNote').textContent = GEO.recs.length + ' TLEs \u00b7 realtime SGP4';
  }catch(e){
    $('geoNote').textContent = 'Map init failed';
  }
}

function geoScan(){
  /* find satellites currently inside the viewport (+margin), chunked */
  const now = new Date();
  const gmst = satellite.gstime(now);
  const hlat = geoHLat() + 6, hlon = GEO.HLON + 8;
  const found = [];
  let i = 0;
  const step = () => {
    const end = Math.min(i + 1200, GEO.recs.length);
    for (; i < end; i++){
      const s = GEO.recs[i];
      try{
        const pv = satellite.propagate(s.rec, now);
        if (!pv.position) continue;
        const gd = satellite.eciToGeodetic(pv.position, gmst);
        const lat = satellite.degreesLat(gd.latitude);
        const lon = satellite.degreesLong(gd.longitude);
        if (Math.abs(lat - GEO.lat0) < hlat){
          let dl = lon - GEO.lon0;
          if (dl > 180) dl -= 360; if (dl < -180) dl += 360;
          if (Math.abs(dl) < hlon) found.push(s);
        }
      }catch(e){}
    }
    if (i < GEO.recs.length) setTimeout(step, 0);
    else { GEO.work = found; GEO.lastScan = Date.now(); }
  };
  step();
}

function geoTick(){
  if (!GEO.inited || !GEO.off) return;
  if (Date.now() - GEO.lastScan > 30_000) geoScan();

  const now = new Date();
  const gmst = satellite.gstime(now);
  const cv = $('geoMap'), x = cv.getContext('2d');
  x.setTransform(2, 0, 0, 2, 0, 0);
  x.drawImage(GEO.off, 0, 0);
  x.font = "10px 'D-DIN',Arial,sans-serif";
  x.textBaseline = 'middle';

  const bestNorad = SKY.data?.best_norad ?? null;
  const pts = [];
  for (const s of GEO.work){
    let pv;
    try{ pv = satellite.propagate(s.rec, now); }catch(e){ continue; }
    if (!pv.position) continue;
    const gd = satellite.eciToGeodetic(pv.position, gmst);
    const lat = satellite.degreesLat(gd.latitude);
    const lon = satellite.degreesLong(gd.longitude);
    const [px, py] = geoProj(lat, lon);
    if (px < -10 || px > GEO.W + 10 || py < -10 || py > GEO.H + 10) continue;
    let el = -90, az = 0;
    try{
      const look = satellite.ecfToLookAngles(GEO.obsGd,
        satellite.eciToEcf(pv.position, gmst));
      el = look.elevation * 180 / Math.PI;
      az = look.azimuth * 180 / Math.PI;
    }catch(e){}
    pts.push({s, lat, lon, px, py, el, az, alt: gd.height});
  }

  /* QTH marker */
  const [qx, qy] = geoProj(GEO.lat0, GEO.lon0);
  x.strokeStyle = '#ffffff';
  x.lineWidth = 1;
  x.strokeRect(qx - 5, qy - 5, 10, 10);
  x.beginPath();
  x.moveTo(qx - 8, qy); x.lineTo(qx - 5, qy);
  x.moveTo(qx + 5, qy); x.lineTo(qx + 8, qy);
  x.moveTo(qx, qy - 8); x.lineTo(qx, qy - 5);
  x.moveTo(qx, qy + 5); x.lineTo(qx, qy + 8);
  x.stroke();

  /* satellites */
  const labeled = pts.filter(p => p.el >= 25).sort((a, b) => b.el - a.el).slice(0, 8);
  for (const p of pts){
    const best = p.s.norad === bestNorad;
    x.beginPath();
    x.arc(p.px, p.py, best ? 4.5 : (p.el >= 25 ? 2.6 : 1.7), 0, Math.PI * 2);
    x.fillStyle = best ? '#ffffff' : (p.el >= 25 ? '#c8ccd2' : '#565b63');
    x.fill();
    if (best){
      x.beginPath();
      x.arc(p.px, p.py, 8.5, 0, Math.PI * 2);
      x.strokeStyle = '#ffffff';
      x.stroke();
      x.strokeStyle = 'rgba(255,255,255,.4)';
      x.beginPath(); x.moveTo(qx, qy); x.lineTo(p.px, p.py); x.stroke();
    }
  }
  x.fillStyle = '#c8ccd2';
  x.textAlign = 'left';
  for (const p of labeled){
    if (p.s.norad === bestNorad) x.fillStyle = '#ffffff';
    x.fillText(p.s.name.replace('STARLINK-', ''), p.px + 8, p.py);
    if (p.s.norad === bestNorad) x.fillStyle = '#c8ccd2';
  }

  /* clock + counts */
  x.fillStyle = '#565b63';
  x.textAlign = 'right';
  x.fillText(now.toISOString().slice(11, 19) + ' UTC', GEO.W - 10, 14);
  x.textAlign = 'left';
  x.fillText(pts.length + ' IN VIEW \u00b7 ' +
    pts.filter(p => p.el >= 25).length + ' \u2265 25\u00b0', 10, 14);

  GEO.pts = pts;
}

function setGeo(on){
  if (on){
    geoInit();
    if (!GEO.timer) GEO.timer = setInterval(geoTick, 1000);
    geoTick();
  } else if (GEO.timer){
    clearInterval(GEO.timer);
    GEO.timer = null;
  }
}

$('geoMap').addEventListener('mousemove', ev => {
  const rect = ev.target.getBoundingClientRect();
  const sc = GEO.W / rect.width;
  const mx = (ev.clientX - rect.left) * sc;
  const my = (ev.clientY - rect.top) * sc;
  let hit = null, dmin = 10;
  for (const p of GEO.pts){
    const d = Math.hypot(p.px - mx, p.py - my);
    if (d < dmin){ dmin = d; hit = p; }
  }
  $('geoCap').innerHTML = hit
    ? `<b>${esc(hit.s.name)}</b> \u00b7 NORAD ${hit.s.norad} \u00b7 ` +
      `${hit.lat.toFixed(2)}\u00b0 ${hit.lon.toFixed(2)}\u00b0 \u00b7 ` +
      `Alt ${hit.alt.toFixed(0)} km \u00b7 ` +
      `Ground ${haversineKm(GEO.lat0, GEO.lon0, hit.lat, hit.lon).toFixed(0)} km \u00b7 ` +
      (hit.el > -90 ? `El ${hit.el.toFixed(1)}\u00b0 Az ${((hit.az + 360) % 360).toFixed(1)}\u00b0` : '') +
      (hit.s.norad === (SKY.data?.best_norad ?? -1) ? ' \u00b7 SERVING CANDIDATE' : '')
    : 'Hover a satellite for details';
});

/* ================= dish LIVE ================= */
const LIVE_MAX = 90;           // ~3 min at 2s
let LIVE_MS = 2000;
const liveBuf = {t:[], d:[], u:[]};
let liveTimer = null;
let liveChartMade = false;

function ensureLiveChart(){
  if (liveChartMade) return;
  liveChartMade = true;
  const ctx = $('chartLive').getContext('2d');
  const o = baseOpts();
  o.animation = false;
  o.plugins.legend.display = true;
  charts.live = new Chart(ctx, {type:'line', data:{labels:liveBuf.t, datasets:[
    line('Downlink', liveBuf.d, '#ffffff', {fill:true, backgroundColor:faintFill(ctx)}),
    line('Uplink', liveBuf.u, css('--dim')),
  ]}, options:o});
}

async function pollLive(){
  try{
    const res = await fetch('dish_live.php', {cache:'no-store'});
    if (!res.ok) throw new Error('http ' + res.status);
    const d = await res.json();
    if (d.error) throw new Error(d.error);

    $('dDown').textContent = d.down_mbps.toFixed(1);
    $('dUp').textContent = d.up_mbps.toFixed(1);
    $('dPop').textContent = d.pop_ms.toFixed(0);
    $('liveDot').classList.remove('err');
    $('dDownSub').textContent = 'Realtime dish telemetry';

    const ic = {
      clock:'<svg class="ic" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>',
      sat:'<svg class="ic" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2.2"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/></svg>',
      eth:'<svg class="ic" viewBox="0 0 24 24"><rect x="4" y="9" width="16" height="9" rx="1"/><path d="M8 9V6h8v3M9 18v2M15 18v2"/></svg>',
      obstr:'<svg class="ic" viewBox="0 0 24 24"><path d="M12 3a7 7 0 0 1 7 7c0 5-7 11-7 11S5 15 5 10a7 7 0 0 1 7-7z"/></svg>',
      angle:'<svg class="ic" viewBox="0 0 24 24"><path d="M4 20h16M4 20L16 6"/><path d="M9 20a8 8 0 0 0-1.6-4.8"/></svg>',
      chip:'<svg class="ic" viewBox="0 0 24 24"><rect x="7" y="7" width="10" height="10"/><path d="M10 3v4M14 3v4M10 17v4M14 17v4M3 10h4M3 14h4M17 10h4M17 14h4"/></svg>',
    };
    $('dishMeta').innerHTML = [
      `${ic.clock}Uptime <b>${fmtUptime(d.uptime_s)}</b>`,
      `${ic.sat}GPS <b>${d.gps_sats} sats</b>`,
      `${ic.eth}Eth <b>${d.eth_mbps} Mbps</b>`,
      `${ic.obstr}Obstruction <b>${d.obstr_pct}%</b>`,
      `${ic.angle}Tilt <b>${d.tilt}°</b> · Az <b>${d.azim}°</b> · El <b>${d.elev}°</b>`,
      `${ic.chip}FW <b>${esc(d.sw)}</b>`,
    ].map(s => `<span>${s}</span>`).join('');

    const ar = $('dishAlerts');
    if (d.alerts && d.alerts.length){
      ar.classList.add('on');
      $('dishAlertsText').textContent = 'Active alerts: ' + d.alerts.join(' · ');
    } else {
      ar.classList.remove('on');
    }

    ensureLiveChart();
    liveBuf.t.push(new Date(d.ts * 1000).toLocaleTimeString([], {minute:'2-digit', second:'2-digit'}));
    liveBuf.d.push(d.down_mbps);
    liveBuf.u.push(d.up_mbps);
    if (liveBuf.t.length > LIVE_MAX){
      liveBuf.t.shift(); liveBuf.d.shift(); liveBuf.u.shift();
    }
    charts.live.update('none');
  }catch(e){
    $('liveDot')?.classList.add('err');
    $('dDownSub').textContent = 'Dish unreachable';
  }
}

function setLive(on){
  if (on && !liveTimer){
    $('liveBadgeTxt').textContent = 'LIVE · ' + Math.round(LIVE_MS / 1000) + 'S';
    pollLive();
    liveTimer = setInterval(pollLive, LIVE_MS);
  } else if (!on && liveTimer){
    clearInterval(liveTimer);
    liveTimer = null;
  }
}

document.addEventListener('visibilitychange', () => {
  setLive(!document.hidden && activeView === 'dish');
  setGeo(!document.hidden && activeView === 'sats');
  hzSetLive(!document.hidden && activeView === 'dash');
});

/* ================= debug & settings ================= */
let dbgAuthed = false;
let authSetup = false;   // true until an admin password has been created

async function dbgApi(action, extra = {}){
  const res = await fetch('debug.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body:JSON.stringify(Object.assign({action}, extra)),
  });
  const j = await res.json().catch(() => ({error:'bad response'}));
  if (res.status === 401){ dbgAuthed = false; syncLocks(); }
  if (!res.ok) throw new Error(j.error || ('http ' + res.status));
  return j;
}

function syncLocks(){
  $('dbgLock').hidden = dbgAuthed;  $('dbgTools').hidden = !dbgAuthed;
  $('setLock').hidden = dbgAuthed;  $('setTools').hidden = !dbgAuthed;
  const t = authSetup ? 'First run — choose an admin password (min 8 chars)'
                      : 'Restricted — enter password';
  const b = authSetup ? 'Set password' : 'Unlock';
  $('dbgLockT').textContent = t;  $('setLockT').textContent = t;
  $('dbgUnlock').textContent = b; $('setUnlock').textContent = b;
  if (dbgAuthed) loadSettings();
}

async function checkAuth(){
  try{
    const s = await dbgApi('status');
    dbgAuthed = s.auth;
    authSetup = !!s.setup;
  }catch(e){ dbgAuthed = false; }
  syncLocks();
}

async function tryUnlock(passEl, errEl){
  errEl.textContent = '';
  passEl.classList.remove('no');
  try{
    await dbgApi(authSetup ? 'setup_password' : 'login', {password: passEl.value});
    passEl.value = '';
    dbgAuthed = true;
    authSetup = false;
    /* fade the curtains, then reveal */
    ['dbgLock', 'setLock'].forEach(id => $(id).classList.add('bye'));
    setTimeout(() => {
      syncLocks();
      ['dbgLock', 'setLock'].forEach(id => $(id).classList.remove('bye'));
    }, 450);
  }catch(e){
    errEl.textContent = e.message || 'Wrong password';
    void passEl.offsetWidth;      // restart animation
    passEl.classList.add('no');
    passEl.select();
  }
}
$('dbgUnlock').addEventListener('click', () => tryUnlock($('dbgPass'), $('dbgErr')));
$('setUnlock').addEventListener('click', () => tryUnlock($('setPass'), $('setErr')));
$('dbgPass').addEventListener('keydown', e => { if (e.key === 'Enter') tryUnlock($('dbgPass'), $('dbgErr')); });
$('setPass').addEventListener('keydown', e => { if (e.key === 'Enter') tryUnlock($('setPass'), $('setErr')); });
$('dbgLogout').addEventListener('click', async () => {
  try{ await dbgApi('logout'); }catch(e){}
  dbgAuthed = false; syncLocks();
});

/* ---- tools ---- */
function showOut(id){
  ['dbgPingOut','dbgMtrOut','dbgDnsOut','dbgHttpOut'].forEach(x => $(x).hidden = (x !== id));
}

async function runTool(tool){
  const t = $('dbgTarget').value.trim();
  const count = parseInt($('dbgCount').value, 10);
  const st = $('dbgStatus');
  st.textContent = 'Running ' + tool.toUpperCase() + ' \u2192 ' + t + ' \u2026';
  document.querySelectorAll('[data-tool]').forEach(b => b.disabled = true);
  try{
    if (tool === 'ping'){
      const d = await dbgApi('ping', {target:t, count});
      renderDbgPing(d);
    } else if (tool === 'mtr'){
      const d = await dbgApi('mtr', {target:t, count:Math.min(count, 10)});
      renderMtr(d);
    } else if (tool === 'dns'){
      const d = await dbgApi('dns', {target:t.replace(/^https?:\/\//,'').split('/')[0]});
      renderDns(d);
    } else if (tool === 'http'){
      const url = /^https?:\/\//.test(t) ? t : 'https://' + t;
      const d = await dbgApi('http', {url});
      renderHttp(d);
    }
    st.textContent = '';
  }catch(e){
    st.textContent = 'Error: ' + e.message;
  }
  document.querySelectorAll('[data-tool]').forEach(b => b.disabled = false);
}
document.querySelectorAll('[data-tool]').forEach(b =>
  b.addEventListener('click', () => runTool(b.dataset.tool)));
$('dbgTarget').addEventListener('keydown', e => { if (e.key === 'Enter') runTool('ping'); });

function renderDbgPing(d){
  showOut('dbgPingOut');
  const s = d.summary || {};
  $('pingTitle').textContent = d.target + (s.ip && s.ip !== d.target ? ' (' + s.ip + ')' : '');
  $('pingStats').innerHTML = `
    <div class="stat"><div class="k">Sent / Recv</div><div class="v">${s.sent ?? '–'} / ${s.recv ?? '–'}</div></div>
    <div class="stat"><div class="k">Loss</div><div class="v ${s.loss > 0 ? 'bad' : ''}">${s.loss ?? '–'}<small>%</small></div></div>
    <div class="stat"><div class="k">Min / Avg</div><div class="v">${s.min ?? '–'} / ${s.avg ?? '–'}<small>ms</small></div></div>
    <div class="stat"><div class="k">Max / Mdev</div><div class="v">${s.max ?? '–'} / ${s.mdev ?? '–'}<small>ms</small></div></div>`;
  const labels = d.rtts.map((_, i) => '#' + (i + 1));
  mk('chartDbgPing', {type:'bar', data:{labels, datasets:[{
    label:'RTT (ms)',
    data:d.rtts.map(v => v ?? 0),
    backgroundColor:d.rtts.map(v => v == null ? css('--red') : '#ffffff'),
    borderRadius:0, maxBarThickness:26,
  }]}, options:(() => { const o = baseOpts(); o.plugins.legend.display = false; return o; })()});
}

function renderMtr(d){
  showOut('dbgMtrOut');
  $('mtrTitle').textContent = d.target + ' \u00b7 ' + d.hops.length + ' hops';
  const maxAvg = Math.max(1, ...d.hops.map(h => h.avg ?? 0));
  $('mtrHops').innerHTML = d.hops.map(h => {
    const lost = h.ip == null;
    const asn = h.asn
      ? `<span class="asn"><b>AS${h.asn}</b>${h.org ? ' \u00b7 ' + esc(h.org) : ''}${h.cc ? ' \u00b7 ' + esc(h.cc) : ''}</span>`
      : (lost ? '' : `<span class="asn">NO ASN</span>`);
    const stats = lost ? `<div class="h-stats"><span class="lossy">Loss <b>100%</b></span></div>` : `
      <div class="h-stats">
        <span class="${h.loss > 0 ? 'lossy' : ''}">Loss <b>${h.loss}%</b></span>
        <span>Last <b>${h.last}</b></span>
        <span>Avg <b>${h.avg}</b></span>
        <span>Best <b>${h.best}</b></span>
        <span>Worst <b>${h.wrst}</b></span>
      </div>
      <div class="hbar">
        <div class="f" style="width:${Math.max(2, (h.avg / maxAvg) * 100)}%"></div>
        ${h.loss > 0 ? `<div class="l" style="width:${h.loss}%"></div>` : ''}
      </div>`;
    return `<div class="hop">
      <div class="rail"><div class="nd ${lost ? 'lost' : ''}"></div><div class="ln"></div></div>
      <div class="card">
        <div class="h-top">
          <span class="h-n">HOP ${String(h.hop).padStart(2, '0')}</span>
          <span class="h-ip">${lost ? '\u2014 no reply \u2014' : h.ip}</span>
          ${asn}
          ${h.ptr ? `<span class="h-ptr">${esc(h.ptr)}</span>` : ''}
        </div>
        ${stats}
      </div>
    </div>`;
  }).join('');
}

function renderDns(d){
  showOut('dbgDnsOut');
  $('dnsTitle').textContent = d.target;
  $('dnsBody').innerHTML = (d.records || []).map(r => `
    <tr><td>${r.type}</td>
    <td class="dim" style="white-space:normal">${r.values.map(esc).join('<br>')}</td>
    <td class="r">${r.ms} <span class="mut">ms</span></td></tr>`).join('')
    || `<tr><td colspan="3" class="empty">No records</td></tr>`;
}

function renderHttp(d){
  showOut('dbgHttpOut');
  $('httpTitle').textContent = d.url;
  $('httpStats').innerHTML = `
    <div class="stat"><div class="k">Status / IP</div>
      <div class="v" style="font-size:16px;margin-top:10px">${d.code} \u00b7 ${d.ip}</div></div>
    <div class="stat"><div class="k">Total</div><div class="v">${d.total}<small>ms</small></div></div>
    <div class="stat"><div class="k">TTFB</div><div class="v">${d.ttfb}<small>ms</small></div></div>
    <div class="stat"><div class="k">Transfer</div>
      <div class="v">${d.mbps}<small>Mbps</small></div></div>`;
  const segs = [
    ['DNS', d.dns, ''], ['TCP', d.tcp, 'dim'], ['TLS', d.tls, 'dark'],
    ['TTFB', d.ttfb, ''], ['Body', d.transfer, 'dim'],
  ];
  const total = Math.max(1, d.total);
  let acc = 0;
  $('httpWf').innerHTML = segs.map(([name, ms, cls]) => {
    const left = acc / total * 100, w = Math.max(0.5, ms / total * 100);
    acc += ms;
    return `<div class="row">
      <div class="lbl">${name}</div>
      <div class="track"><div class="seg ${cls}" style="left:${left}%;width:${w}%"></div></div>
      <div class="ms">${ms} ms</div>
    </div>`;
  }).join('');
}

/* ---- ICMP targets in settings ---- */
/* Well-known anycast resolvers, plus the two hops that are specific to a
   Starlink install: the dish itself and the CGNAT gateway behind it. Pinging
   those two separates "my link to the PoP" from "the internet beyond it". */
const HZ_PRESETS = [
  {label:'Cloudflare',  host:'1.1.1.1'},
  {label:'Cloudflare 2',host:'1.0.0.1'},
  {label:'Google',      host:'8.8.8.8'},
  {label:'Google 2',    host:'8.8.4.4'},
  {label:'Quad9',       host:'9.9.9.9'},
  {label:'Quad9 2',     host:'149.112.112.112'},
  {label:'OpenDNS',     host:'208.67.222.222'},
  {label:'AdGuard',     host:'94.140.14.14'},
  {label:'Lumen',       host:'4.2.2.1'},
  {label:'Starlink Dish',host:'192.168.100.1'},
  {label:'Starlink GW', host:'100.64.0.1'},
];
const HZ_MAX = 8;
let hzTargets = [];

function hzSyncTargets(){
  const prov = $('hzProv');
  if (prov && !prov.dataset.built){
    prov.dataset.built = '1';
    prov.innerHTML = HZ_PRESETS.map((p, i) =>
      `<button type="button" data-p="${i}">${p.label}</button>`).join('');
    prov.querySelectorAll('button').forEach(b =>
      b.addEventListener('click', () => {
        const p = HZ_PRESETS[+b.dataset.p];
        const at = hzTargets.findIndex(t => t.host === p.host);
        if (at >= 0) hzTargets.splice(at, 1);
        else if (hzTargets.length >= HZ_MAX)
          return void ($('hzStatus').textContent = `Maximum ${HZ_MAX} targets`);
        else hzTargets.push({label:p.label, host:p.host});
        $('hzStatus').textContent = '';
        hzSyncTargets();
      }));
  }
  prov?.querySelectorAll('button').forEach(b => b.classList.toggle('on',
    hzTargets.some(t => t.host === HZ_PRESETS[+b.dataset.p].host)));

  const list = $('hzTargets');
  if (!list) return;
  list.innerHTML = hzTargets.length
    ? hzTargets.map((t, i) => `
        <div class="tgtrow">
          <span class="nm">${esc(t.label)}</span><span class="ip">${esc(t.host)}</span>
          <button class="rm" data-i="${i}" title="Remove">&times;</button>
        </div>`).join('')
    : '<div class="tgtrow"><span class="ip">No targets — the card stays hidden</span></div>';
  list.querySelectorAll('.rm').forEach(b =>
    b.addEventListener('click', () => {
      hzTargets.splice(+b.dataset.i, 1); hzSyncTargets();
    }));
}

$('hzAdd')?.addEventListener('click', () => {
  const host = $('hzCustomHost').value.trim();
  const label = $('hzCustomName').value.trim() || host;
  const st = $('hzStatus');
  if (!/^[A-Za-z0-9]([A-Za-z0-9.\-:]*[A-Za-z0-9])?$/.test(host))
    return void (st.textContent = 'Enter a valid IP or hostname');
  if (hzTargets.some(t => t.host === host))
    return void (st.textContent = 'Already in the list');
  if (hzTargets.length >= HZ_MAX)
    return void (st.textContent = `Maximum ${HZ_MAX} targets`);
  hzTargets.push({label: label.slice(0, 40), host});
  $('hzCustomHost').value = ''; $('hzCustomName').value = '';
  st.textContent = ''; hzSyncTargets();
});

/* ---- settings ---- */
async function loadSettings(){
  try{
    const c = await dbgApi('get_config');
    $('tgToken').value = c.telegram.token;
    $('tgChat').value = c.telegram.chat_id;
    $('alFail').checked = c.alerts.test_fail;
    $('alRetry').checked = c.alerts.retry;
    $('alLow').checked = c.alerts.low_speed;
    $('alLowMbps').value = c.alerts.low_speed_mbps;
    $('alDown').checked = c.alerts.dish_down;
    $('alHw').checked = c.alerts.dish_hw;
    $('alDrop').checked = c.alerts.high_drop;
    $('alDropPct').value = c.alerts.drop_pct;
    $('alIp').checked = c.alerts.new_ip;
    $('ivSp').value = c.intervals.speedtest_min;
    $('ivDish').value = c.intervals.dish_s;
    $('ivSats').value = c.intervals.sats_s;
    $('ivLive').value = c.intervals.live_poll_s;
    const us = c.usage || {};
    $('usDay').value = us.cycle_day ?? 1;
    $('usCap').value = us.cap_gb ?? 0;
    const eg = c.energy || {};
    $('enPrice').value = eg.price_per_kwh ?? 0;
    $('enCur').value = eg.currency ?? 'EUR';
    const ic = c.icmp || {};
    $('hzEn').checked = ic.enabled !== false;
    $('hzGood').value = ic.good_ms ?? 40;
    $('hzWarn').value = ic.warn_ms ?? 100;
    $('hzInt').value  = ic.interval_s ?? 30;
    $('hzCnt').value  = ic.count ?? 5;
    hzTargets = Array.isArray(ic.targets)
      ? ic.targets.filter(t => t && t.host).map(t => ({label:t.label || t.host, host:t.host}))
      : [];
    hzSyncTargets();
    $('ivSane').value = c.speedtest.min_sane_mbps;
    $('ivAtt').value = c.speedtest.max_attempts;
    $('locLat').value = c.location?.lat ?? '';
    $('locLon').value = c.location?.lon ?? '';
    $('locAlt').value = c.location?.alt_m ?? 0;
    $('retDays').value = c.retention?.days ?? 0;
  }catch(e){}
}

$('setSave').addEventListener('click', async () => {
  const st = $('setStatus');
  st.textContent = 'Saving\u2026';
  try{
    const r = await dbgApi('save_config', {config:{
      telegram:{token:$('tgToken').value.trim(), chat_id:$('tgChat').value.trim()},
      alerts:{
        test_fail:$('alFail').checked, retry:$('alRetry').checked,
        low_speed:$('alLow').checked, low_speed_mbps:parseFloat($('alLowMbps').value),
        dish_down:$('alDown').checked, dish_hw:$('alHw').checked,
        high_drop:$('alDrop').checked, drop_pct:parseFloat($('alDropPct').value),
        new_ip:$('alIp').checked,
      },
      intervals:{
        speedtest_min:parseInt($('ivSp').value, 10),
        dish_s:parseInt($('ivDish').value, 10),
        sats_s:parseInt($('ivSats').value, 10),
        live_poll_s:parseInt($('ivLive').value, 10),
      },
      energy:{
        price_per_kwh:parseFloat($('enPrice').value),
        currency:$('enCur').value.trim(),
      },
      usage:{
        cycle_day:parseInt($('usDay').value, 10),
        cap_gb:parseFloat($('usCap').value),
      },
      icmp:{
        enabled:$('hzEn').checked,
        good_ms:parseFloat($('hzGood').value),
        warn_ms:parseFloat($('hzWarn').value),
        interval_s:parseInt($('hzInt').value, 10),
        count:parseInt($('hzCnt').value, 10),
        targets:hzTargets,
      },
      speedtest:{
        min_sane_mbps:parseFloat($('ivSane').value),
        max_attempts:parseInt($('ivAtt').value, 10),
      },
      location:{
        lat:$('locLat').value === '' ? null : parseFloat($('locLat').value),
        lon:$('locLon').value === '' ? null : parseFloat($('locLon').value),
        alt_m:parseFloat($('locAlt').value) || 0,
      },
      retention:{days:parseInt($('retDays').value, 10) || 0},
    }});
    st.textContent = r.note || 'Saved';
    LIVE_MS = Math.max(1000, parseInt($('ivLive').value, 10) * 1000);
    hzPoll();
    loadUsage();
    loadEnergy();
    if (liveTimer){ setLive(false); setLive(activeView === 'dish'); }
  }catch(e){ st.textContent = 'Error: ' + e.message; }
});

$('tgValidate').addEventListener('click', async () => {
  const st = $('tgStatus');
  st.textContent = 'Checking token\u2026';
  try{
    const r = await dbgApi('tg_get_me', {token:$('tgToken').value.trim()});
    st.textContent = r.ok ? 'Token OK \u2014 bot @' + r.result.username : 'Invalid token';
  }catch(e){ st.textContent = 'Error: ' + e.message; }
});

$('tgGetChat').addEventListener('click', async () => {
  const st = $('tgStatus');
  st.textContent = 'Fetching updates\u2026';
  $('tgChats').innerHTML = '';
  try{
    const r = await dbgApi('tg_updates', {token:$('tgToken').value.trim()});
    if (!r.chats.length){
      st.textContent = r.hint || 'No chats found';
      return;
    }
    st.textContent = 'Pick a chat:';
    $('tgChats').innerHTML = r.chats.map(c =>
      `<button data-id="${c.id}">${c.id} \u00b7 ${c.name || '?'} \u00b7 ${c.type}</button>`).join('');
    $('tgChats').querySelectorAll('button').forEach(b =>
      b.addEventListener('click', () => {
        $('tgChat').value = b.dataset.id;
        st.textContent = 'Chat ID set \u2014 remember to Save';
        $('tgChats').innerHTML = '';
      }));
  }catch(e){ st.textContent = 'Error: ' + e.message; }
});

$('tgTest').addEventListener('click', async () => {
  const st = $('tgStatus');
  st.textContent = 'Sending test\u2026';
  try{
    const r = await dbgApi('tg_test',
      {token:$('tgToken').value.trim(), chat_id:$('tgChat').value.trim()});
    st.textContent = r.ok ? 'Test message sent \u2705' : 'Send failed';
  }catch(e){ st.textContent = 'Error: ' + e.message; }
});

$('pwChange').addEventListener('click', async () => {
  const st = $('pwStatus');
  st.textContent = 'Saving\u2026';
  try{
    await dbgApi('change_password', {password:$('pwNew').value});
    $('pwNew').value = '';
    st.textContent = 'Password changed';
  }catch(e){ st.textContent = 'Error: ' + e.message; }
});

/* ================= energy ================= */
const EN = {data:null};

function kwh(w, dp = 2){ return w == null ? '–' : (w / 1000).toFixed(dp); }
function money(kw, price, cur){
  if (!price) return null;
  const v = kw * price;
  return (v < 10 ? v.toFixed(2) : v.toFixed(1)) + (cur ? ' ' + cur : '');
}

function renderEnergy(d){
  EN.data = d;
  const off = $('enOff'), on = $('enOn');
  if (!d || !d.supported){
    off.hidden = false; on.hidden = true;
    off.textContent = d && d.waiting
      ? 'Waiting for the first power sample from the dish'
      : 'This dish does not report input power on its firmware';
    return;
  }
  off.hidden = true; on.hidden = false;

  const L = d.latest || {}, st = d.stats || {};
  const w = L.power_w;
  $('enNow').textContent = w != null ? w.toFixed(0) : '–';

  /* Scale the bar against the observed maximum so it means something for
     this dish rather than against an arbitrary ceiling. */
  const top = Math.max(st.max_w || 0, w || 0, 1);
  const bar = $('enBar');
  bar.style.width = Math.min(100, 100 * (w || 0) / top) + '%';
  bar.className = L.heating ? 'heat' : '';
  if (st.idle_w != null){
    const m = $('enIdleMark');
    m.hidden = false;
    m.style.left = Math.min(100, 100 * st.idle_w / top) + '%';
    m.title = `Idle ${st.idle_w} W`;
  }
  $('enHeat').classList.toggle('off', !L.heating);
  $('enNowSub').innerHTML = st.idle_w != null
    ? `Idle <b>${st.idle_w}</b> W · Peak <b>${st.max_w}</b> W`
    : '&nbsp;';

  $('enToday').textContent = kwh(d.today_wh);
  const dy = d.yesterday_wh, tw = d.today_wh;
  $('enTodaySub').innerHTML = dy
    ? `Yesterday <b>${kwh(dy)}</b> kWh` + deltaHtml(tw, dy, false, ' Wh')
    : '&nbsp;';

  $('enMonth').textContent = kwh(d.month_wh);
  $('enMonthSub').innerHTML = `Projected <b>${kwh(d.projected_wh)}</b> kWh`
    + (d.avg_day_wh ? ` · <b>${kwh(d.avg_day_wh, 2)}</b> kWh/day` : '');

  const sp = [];
  if (L.dish_power_w != null && L.dish_power_w > 0)
    sp.push(`Dish <b>${L.dish_power_w.toFixed(1)} W</b>`);
  if (L.router_power_w != null && L.router_power_w > 0)
    sp.push(`Router <b>${L.router_power_w.toFixed(1)} W</b>`);
  if (st.heating_s) sp.push(`Snow melt <b>${dur(st.heating_s)}</b> in range`);
  sp.push(`<b>${st.samples}</b> samples`);
  $('enSplit').innerHTML = sp.map(x => `<span>${x}</span>`).join('');

  /* --- power over time, with heating shaded --- */
  const labels = d.series.map(p => fmtTs(p.ts));
  const heatIdx = new Set(d.series.map((p, i) => p.heating ? i : -1).filter(i => i >= 0));
  $('enHeatNote').textContent = heatIdx.size
    ? heatIdx.size + ' buckets with snow melt' : '';
  const o = baseOpts();
  o.scales.y.ticks.callback = v => v + ' W';
  o.plugins.tooltip.callbacks = {
    label: c => `${c.dataset.label}: ${c.parsed.y} W`
      + (heatIdx.has(c.dataIndex) ? ' · heating' : ''),
  };
  const ctxP = $('chartPower').getContext('2d');
  mk('chartPower', {type:'line', data:{labels, datasets:[
    line('Average', d.series.map(p => p.w), '#ffffff',
      {fill:true, backgroundColor:faintFill(ctxP)}),
    line('Peak', d.series.map(p => p.wmax), css('--text3'), {borderDash:[3,3]}),
    /* Heating shows as amber points on the average line rather than a second
       series, so the shape of the curve stays readable. */
    {label:'Snow melt', type:'line', data:d.series.map((p, i) => heatIdx.has(i) ? p.w : null),
     borderColor:'transparent', backgroundColor:css('--warn'),
     pointRadius:3, pointHoverRadius:5, showLine:false, spanGaps:false},
  ]}, options:o});

  /* --- per-day kWh --- */
  const od = baseOpts();
  od.plugins.legend.display = false;
  od.scales.y.ticks.callback = v => (v / 1000).toFixed(1) + ' kWh';
  od.plugins.tooltip.callbacks = {
    label: c => `${(c.parsed.y / 1000).toFixed(3)} kWh`
      + (d.days[c.dataIndex]?.heat_s ? ` · melt ${dur(d.days[c.dataIndex].heat_s)}` : ''),
  };
  mk('chartDayWh', {type:'bar', data:{labels:d.days.map(x => x.day.slice(5)), datasets:[{
    label:'kWh', data:d.days.map(x => x.wh),
    backgroundColor:d.days.map(x => x.heat_s ? css('--warn') : '#ffffff'),
    borderRadius:0, maxBarThickness:22,
  }]}, options:od});

  $('enStatNote').textContent = d.range.toUpperCase() + ' · bucket ' + dur(d.bucket);
  $('enStats').innerHTML = `
    <div class="stat"><div class="k">Idle Draw</div><div class="v">${st.idle_w ?? '–'}<small>W</small></div></div>
    <div class="stat"><div class="k">Average</div><div class="v">${st.avg_w ?? '–'}<small>W</small></div></div>
    <div class="stat"><div class="k">Peak</div><div class="v">${st.max_w ?? '–'}<small>W</small></div></div>
    <div class="stat"><div class="k">Total In Range</div><div class="v">${kwh(st.total_wh)}<small>kWh</small></div></div>
    <div class="stat"><div class="k">Per Day</div><div class="v">${kwh(d.avg_day_wh, 2)}<small>kWh</small></div></div>
    <div class="stat"><div class="k">Per Year</div><div class="v">${kwh(d.avg_day_wh * 365, 0)}<small>kWh</small></div></div>
    <div class="stat"><div class="k">Snow Melt</div>
      <div class="v ${st.heating_s ? 'bad' : ''}">${st.heating_s ? dur(st.heating_s) : 'none'}</div></div>
    <div class="stat"><div class="k">Samples</div><div class="v">${st.samples}</div></div>`;

  /* --- cost --- */
  const card = $('enCostCard');
  if (d.price){
    card.hidden = false;
    const perDay = d.avg_day_wh / 1000, cur = d.currency;
    $('enPriceNote').textContent = `${d.price} ${cur}/kWh`;
    $('enCost').innerHTML = `
      <div class="usum"><div class="k">Today</div>
        <div class="v">${money(d.today_wh / 1000, d.price, cur)}</div></div>
      <div class="usum"><div class="k">This Month</div>
        <div class="v">${money(d.month_wh / 1000, d.price, cur)}</div></div>
      <div class="usum"><div class="k">Projected Month</div>
        <div class="v">${money(d.projected_wh / 1000, d.price, cur)}</div></div>
      <div class="usum"><div class="k">Per Year</div>
        <div class="v">${money(perDay * 365, d.price, cur)}</div></div>`;
  } else card.hidden = true;
}

async function loadEnergy(){
  try{
    const res = await fetch(withDish('energy.php?range=' + (range === '3h' ? '24h' : range)),
                            {cache:'no-store'});
    const d = await res.json();
    if (d.dishes) renderDishSel(d.dishes);
    renderEnergy(d);
  }catch(e){}
}

/* ================= data usage and outage timeline ================= */
const USG = {data:null, range:'30d'};

const TL_COL = {
  ok:'#2a2a2a', link_outage:'#ff3b30', degraded:'#d6a01d',
  dish_unreachable:'#4a4f57',
};
const TL_LABEL = {
  link_outage:'Link outage', degraded:'Degraded',
  dish_unreachable:'Dish unreachable',
};
/* Causes reported by the dish itself. Anything not listed still renders,
   just with its raw enum name tidied up. */
const CAUSE_LABEL = {
  OBSTRUCTED:'Obstructed', NO_SATS:'No satellites', NO_SCHEDULE:'No schedule',
  NO_DOWNLINK:'No downlink', NO_PINGS:'No pings', SKY_SEARCH:'Sky search',
  THERMAL_SHUTDOWN:'Thermal shutdown', THERMAL_THROTTLE:'Thermal throttle',
  STOWED:'Stowed', BOOTING:'Booting', SLEEPING:'Sleeping',
  ACTUATOR_ACTIVITY:'Actuator activity', CABLE_TEST:'Cable test',
  INHIBIT_RF:'RF inhibited', UNKNOWN:'Unknown',
};
const causeName = c => CAUSE_LABEL[c]
  || (c || '').replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, m => m.toUpperCase());
/* Obstruction is the user's own problem to fix; a thermal or stow event is
   the hardware; the rest is the network. Colouring by that grouping makes
   the timeline answer "whose fault" at a glance. */
const CAUSE_CLASS = {
  OBSTRUCTED:'deg', SKY_SEARCH:'deg',
  THERMAL_SHUTDOWN:'link', THERMAL_THROTTLE:'link', STOWED:'blind',
  BOOTING:'blind', SLEEPING:'blind', CABLE_TEST:'blind', INHIBIT_RF:'blind',
};

function gb(bytes, dp = 2){
  if (bytes == null) return '–';
  if (bytes >= 1e12) return (bytes / 1e12).toFixed(dp) + '<small>TB</small>';
  if (bytes >= 1e9)  return (bytes / 1e9).toFixed(dp) + '<small>GB</small>';
  if (bytes >= 1e6)  return (bytes / 1e6).toFixed(0) + '<small>MB</small>';
  return (bytes / 1e3).toFixed(0) + '<small>KB</small>';
}
function dur(s){
  if (s == null) return '–';
  /* Durations arrive as floats (the dish reports nanoseconds), so round
     before formatting — otherwise a sum lands as 31.099999999999998s. */
  if (s < 60) return (s < 10 ? Math.round(s * 10) / 10 : Math.round(s)) + 's';
  s = Math.round(s);
  if (s < 3600) return Math.floor(s / 60) + 'm ' + (s % 60) + 's';
  if (s < 86400) return Math.floor(s / 3600) + 'h ' + Math.floor((s % 3600) / 60) + 'm';
  return Math.floor(s / 86400) + 'd ' + Math.floor((s % 86400) / 3600) + 'h';
}

/* ---------- usage ---------- */
function renderUsage(u){
  const card = $('usageCard2');
  if (!u || !u.days){ card.hidden = true; return; }
  card.hidden = false;

  const capB = u.cap_gb ? u.cap_gb * 1e9 : null;
  $('usageTiles').innerHTML = `
    <div class="stat"><div class="k">Today</div><div class="v">${gb(u.today)}</div></div>
    <div class="stat"><div class="k">This Cycle</div><div class="v">${gb(u.cycle_total)}</div></div>
    <div class="stat"><div class="k">Projected</div>
      <div class="v ${capB && u.projected > capB ? 'bad' : ''}">${gb(u.projected)}</div></div>
    <div class="stat"><div class="k">Speed Tests</div>
      <div class="v">${gb(u.selftest)}<small>${u.selftest_pct ?? 0}%</small></div></div>`;

  const bar = $('usageBar');
  if (capB){
    bar.hidden = false;
    const pct = Math.min(100, 100 * u.cycle_total / capB);
    const f = $('usageFill');
    f.style.width = pct + '%';
    f.className = pct >= 100 ? 'bad' : (pct >= 80 ? 'warn' : '');
    /* where the projection lands, so overshoot is visible before it happens */
    const pm = $('usageMark');
    const proj = Math.min(100, 100 * u.projected / capB);
    pm.hidden = false;
    pm.style.left = proj + '%';
  } else bar.hidden = true;

  const bits = [];
  if (capB) bits.push(`Cap <b>${u.cap_gb} GB</b>`);
  bits.push(`Cycle from day <b>${u.cycle_day}</b>`);
  /* Usage is only as complete as the dish was reachable — say so rather than
     quietly under-reporting. */
  if (u.coverage_pct != null && u.coverage_pct < 95)
    bits.push(`<span class="warn">Only <b>${u.coverage_pct}%</b> of the cycle was sampled — actual usage is higher</span>`);
  $('usageSub').innerHTML = bits.map(b => `<span>${b}</span>`).join('');
  $('usageNote').textContent = `${u.days.length} days · measured from the dish`;

  const labels = u.days.map(d => d.day.slice(5));
  const o = baseOpts();
  o.scales.x.stacked = true; o.scales.y.stacked = true;
  o.scales.y.ticks.callback = v => (v / 1e9).toFixed(0) + ' GB';
  o.plugins.tooltip.callbacks = {
    label: c => `${c.dataset.label}: ${(c.parsed.y / 1e9).toFixed(2)} GB`,
  };
  mk('chartUsage', {type:'bar', data:{labels, datasets:[
    {label:'Download', data:u.days.map(d => d.down), backgroundColor:'#ffffff',
     borderRadius:0, maxBarThickness:22},
    {label:'Upload', data:u.days.map(d => d.up), backgroundColor:css('--dim'),
     borderRadius:0, maxBarThickness:22},
  ]}, options:o});
}

/* ---------- outage timeline ---------- */
function tlDraw(){
  const d = USG.data;
  const cv = $('tlCanvas');
  if (!cv || !d) return;
  const dpr = Math.min(window.devicePixelRatio || 1, 2);
  const w = cv.clientWidth || 900, h = 58;
  if (cv.width !== Math.round(w * dpr)){ cv.width = Math.round(w * dpr); cv.height = Math.round(h * dpr); }
  const x = cv.getContext('2d');
  x.setTransform(dpr, 0, 0, dpr, 0, 0);
  x.clearRect(0, 0, w, h);

  const t0 = d.since, t1 = d.now, span = Math.max(1, t1 - t0);
  const px = ts => ((ts - t0) / span) * w;
  const BAR_Y = 8, BAR_H = 30;

  /* baseline: assume up, then paint what went wrong over it */
  x.fillStyle = TL_COL.ok;
  x.fillRect(0, BAR_Y, w, BAR_H);

  TL.hit = [];
  /* draw unreachable first so real outages stay visible on top of it */
  const order = ['dish_unreachable', 'degraded', 'link_outage'];
  for (const kind of order){
    for (const e of (d.events || [])){
      if (e.kind !== kind) continue;
      const a = px(e.start), b = px(e.end);
      const wd = Math.max(1.5, b - a);          // a 2 s blip must stay visible
      const byCause = e.cause ? CAUSE_CLASS[e.cause] : null;
      x.fillStyle = byCause === 'deg' ? TL_COL.degraded
                  : byCause === 'blind' ? TL_COL.dish_unreachable
                  : (TL_COL[kind] || TL_COL.ok);
      x.fillRect(a, BAR_Y, wd, BAR_H);
      TL.hit.push({x0:a, x1:a + wd, e});
    }
  }

  /* Day boundaries. Ticks stay on every day, but labels are thinned to
     whatever the width can fit without them running into each other. */
  x.font = "9px 'D-DIN',Arial,sans-serif";
  x.textAlign = 'center';
  const DAY = 86400;
  const days = Math.max(1, Math.ceil(span / DAY));
  const LABEL_PX = 58;
  const every = Math.max(1, Math.ceil(days / Math.max(1, Math.floor(w / LABEL_PX))));
  const hourly = span <= 2 * DAY;          // short ranges label hours instead
  const step = hourly ? 6 * 3600 : DAY;
  const first = Math.ceil(t0 / step) * step;
  let i = 0;
  for (let t = first; t < t1; t += step, i++){
    const p = px(t);
    if (p < 10 || p > w - 10) continue;
    const major = hourly ? true : (i % every === 0);
    x.strokeStyle = major ? 'rgba(255,255,255,.18)' : 'rgba(255,255,255,.07)';
    x.beginPath(); x.moveTo(p, BAR_Y); x.lineTo(p, BAR_Y + BAR_H); x.stroke();
    if (!major) continue;
    x.fillStyle = '#565b63';
    x.fillText(
      new Date(t * 1000).toLocaleString([], hourly
        ? {hour:'2-digit', minute:'2-digit'}
        : {day:'numeric', month:'short'}),
      p, BAR_Y + BAR_H + 13);
  }
}
const TL = {hit:[]};

function renderTimeline(d){
  USG.data = d;
  const av = d.availability, cov = d.coverage_pct;
  $('tlStats').innerHTML = `
    <div class="stat"><div class="k">Link Availability</div>
      <div class="v ${av != null && av < 99.5 ? 'bad' : ''}">${av != null ? av.toFixed(3) : '–'}<small>%</small></div></div>
    <div class="stat"><div class="k">Observed</div>
      <div class="v ${cov != null && cov < 95 ? 'bad' : ''}">${cov != null ? cov.toFixed(1) : '–'}<small>% of range</small></div></div>
    <div class="stat"><div class="k">Link Lost</div><div class="v">${dur(d.lost_s)}</div></div>
    <div class="stat"><div class="k">Incidents</div><div class="v">${(d.events || []).length}</div></div>`;

  $('tlNote').textContent = d.range.toUpperCase()
    + (d.cause_source === 'dish' ? ' · causes reported by the dish' : ' · causes inferred')
    + (cov != null && cov < 95 ? ` · only ${cov.toFixed(0)}% observed` : '');

  const bc = d.by_cause || {};
  const keys = Object.keys(bc);
  $('tlCauses').innerHTML = keys.length
    ? keys.map(c => {
        const cls = CAUSE_CLASS[c] || 'link';
        return `<span><i class="${cls}"></i>${causeName(c)} <b>${dur(Math.round(bc[c]))}</b></span>`;
      }).join('')
    : '';
  $('tlCauses').hidden = !keys.length;
  $('tlFrom').textContent = fmtFull(d.since);
  $('tlTo').textContent = fmtFull(d.now);

  $('tlBody').innerHTML = (d.events || []).map(e => {
    const byKind = {link_outage:'link', dish_unreachable:'blind', degraded:'deg'}[e.kind] || '';
    const cls = e.cause ? (CAUSE_CLASS[e.cause] ?? byKind) : byKind;
    const label = e.cause ? causeName(e.cause) : (TL_LABEL[e.kind] || e.kind);
    return `<tr>
      <td>${fmtFull(e.start)}</td>
      <td><span class="evkind ${cls}">${esc(label)}</span>${
        e.did_switch ? '<span class="mut" style="margin-left:8px">switched</span>' : ''}</td>
      <td class="r">${dur(e.duration_s)}</td>
      <td class="r dim">${e.worst_drop ? e.worst_drop + '%' : '–'}</td>
      <td class="dim" style="white-space:normal;max-width:420px">${e.detail || ''}</td>
    </tr>`;
  }).join('') || `<tr><td colspan="5" class="empty">No incidents in range</td></tr>`;

  tlDraw();
}

$('tlCanvas')?.addEventListener('mousemove', ev => {
  const r = ev.target.getBoundingClientRect();
  const mx = ev.clientX - r.left;
  const hit = TL.hit.find(h => mx >= h.x0 - 2 && mx <= h.x1 + 2);
  $('tlCap').innerHTML = hit
    ? `<b>${esc(hit.e.cause ? causeName(hit.e.cause) : TL_LABEL[hit.e.kind])}</b>`
      + ` · ${fmtFull(hit.e.start)} · lasted <b>${dur(hit.e.duration_s)}</b>`
      + (hit.e.worst_drop ? ` · worst drop ${hit.e.worst_drop}%` : '')
    : (USG.data
        ? `Link up · hover a marker for detail`
        : 'Hover the timeline for detail');
});

async function loadUsage(){
  try{
    const res = await fetch(withDish('usage.php?range=' + (range === '3h' ? '24h' : range)),
                            {cache:'no-store'});
    const d = await res.json();
    if (d.dishes) renderDishSel(d.dishes);
    if (d.error) return;
    renderUsage(d.usage);
    renderTimeline(d);
  }catch(e){}
}

/* ================= dish selection ================= */
/* Every Starlink dish answers on the same fixed address, so telling two apart
   is a networking problem solved outside this program. What the UI does is
   keep each dish's data separate: every dish-derived endpoint is asked for one
   dish at a time, because averaging two links together would produce numbers
   that describe neither. */
let DISH = localStorage.getItem('dishId') || null;
let DISH_LIST = [];

function renderDishSel(list){
  if (!Array.isArray(list) || !list.length) return;
  const same = list.length === DISH_LIST.length
    && list.every((d, i) => d.id === DISH_LIST[i]?.id);
  DISH_LIST = list;
  if (!DISH || !list.some(d => d.id === DISH)) DISH = list[0].id;

  const el = $('dishSel');
  if (!el) return;
  el.classList.toggle('on', list.length > 1);
  if (list.length < 2) return;

  if (!same || el.dataset.n !== String(list.length)){
    el.dataset.n = String(list.length);
    el.innerHTML = '<span class="lbl">Dish</span>' + list.map(d =>
      `<button data-dish="${d.id}">${esc(d.name || d.id)}</button>`).join('');
    el.querySelectorAll('button').forEach(b =>
      b.addEventListener('click', () => {
        if (b.dataset.dish === DISH) return;
        DISH = b.dataset.dish;
        localStorage.setItem('dishId', DISH);
        renderDishSel(DISH_LIST);
        load();
        loadUsage();
        if (activeView === 'energy') loadEnergy();
      }));
  }
  el.querySelectorAll('button').forEach(b =>
    b.classList.toggle('active', b.dataset.dish === DISH));
}

/** Append the selected dish to an endpoint query. */
const withDish = url => DISH ? url + (url.includes('?') ? '&' : '?') + 'dish=' + encodeURIComponent(DISH) : url;

/* ================= tab title and favicon ================= */
/* A monitoring dashboard is usually left open in a background tab, so the
   headline number and a status colour belong where you can see them without
   switching to it. */
const FAVICON = st => 'data:image/svg+xml,' + encodeURIComponent(
  `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">`
  + `<rect width="100" height="100" fill="black"/>`
  + `<circle cx="50" cy="50" r="12" fill="${st}"/></svg>`);

let favEl = null;
function setStatus(state, title){
  favEl = favEl || document.querySelector('link[rel="icon"]');
  const col = {ok:'#ffffff', warn:'#d6a01d', bad:'#ff3b30'}[state] || '#ffffff';
  if (favEl && favEl.dataset.st !== state){
    favEl.dataset.st = state;
    favEl.href = FAVICON(col);
  }
  document.title = title;
}

/* ================= PoP badge ================= */
/* latest.colo is the Cloudflare edge (an IATA code); latest.country is the
   *client's* country from cdn-cgi/trace. Those are usually different — SOF is
   Sofia in Bulgaria while you sit in Romania — so they are shown separately
   instead of being joined into one misleading string. */
function renderPop(latest, cfg){
  const code = (latest.colo || '').toUpperCase();
  const c = (typeof COLOS !== 'undefined' && COLOS[code]) || null;
  $('popFlag').textContent = c ? flagOf(c[1]) : '\uD83C\uDF10';
  /* City plus country reads as a place; a bare city name does not tell you
     it is abroad, which is the whole point of showing it. */
  $('popName').textContent = c ? `${c[0]}, ${c[1]}` : (code || 'Unknown');

  const bits = [];
  if (code && c) bits.push(code);
  const q = cfg && cfg.qth;
  if (c && q){
    /* Group with thin spaces, not toLocaleString: a European locale renders
       15371 as "15.371", which reads as fifteen-point-three. */
    const km = Math.round(kmBetween(q.lat, q.lon, c[2], c[3]));
    bits.push(`<b>${String(km).replace(/\B(?=(\d{3})+(?!\d))/g, '\u2009')}</b> km`);
  }
  $('popSub').innerHTML = bits.join(' \u00b7 ');

  /* Spell the whole thing out on hover. This is the Cloudflare datacentre the
     speed test terminated at — deliberately not called a PoP, because the
     Starlink PoP is a different hop and already has its own latency figure on
     the Dish tab. */
  $('popItem').title = c
    ? `Speed tests are terminating at the Cloudflare edge in `
      + `${c[0]}, ${c[1]} (${code})`
      + (q ? `, about ${Math.round(kmBetween(q.lat, q.lon, c[2], c[3]))} km from your dish` : '')
      + `.\nThis is not your Starlink ground station — see PoP latency on the Dish tab for that.`
      + (latest.country ? `\nYour connection appears to originate in ${latest.country}.` : '')
    : `Speed tests are terminating at Cloudflare edge ${code || '(unknown)'}.`;
}

/* ================= relative clock ================= */
let LAST_TS = null;
function tickAgo(){
  if (!LAST_TS){ return; }
  $('lastRun').textContent = 'Last test ' + agoText(LAST_TS);
}
setInterval(tickAgo, 30_000);

/* ================= ICMP health ================= */
const HZ = {data:null, timer:null, cvs:new Map()};

const HZ_COL = {
  good:'#3fb950', warn:'#d6a01d', bad:'#ff3b30',
  down:'#ff3b30', stale:'#565b63',
};

/* Which band a single sample falls into — same rules as health.php, so the
   strip and the big number can never disagree. */
function hzBand(rtt, loss, good, warn){
  if (rtt == null || loss >= 100) return 'down';
  if (loss > 0) return 'warn';
  if (rtt <= good) return 'good';
  if (rtt <= warn) return 'warn';
  return 'bad';
}

function hzDrawStrip(cv, t, good, warn){
  const dpr = Math.min(window.devicePixelRatio || 1, 2);
  const w = cv.clientWidth || 200, h = cv.clientHeight || 34;
  if (cv.width !== Math.round(w * dpr) || cv.height !== Math.round(h * dpr)){
    cv.width = Math.round(w * dpr); cv.height = Math.round(h * dpr);
  }
  const x = cv.getContext('2d');
  x.setTransform(dpr, 0, 0, dpr, 0, 0);
  x.clearRect(0, 0, w, h);

  const s = t.samples || [];
  /* baseline so an empty strip still reads as a chart, not a blank box */
  x.strokeStyle = 'rgba(255,255,255,.07)';
  x.beginPath(); x.moveTo(0, h - .5); x.lineTo(w, h - .5); x.stroke();
  if (!s.length) return;

  const N = 60;                       // fixed slot count: bars stay put as
  const slot = w / N;                 // samples arrive, instead of resizing
  const bw = Math.max(1.5, slot - 1.5);
  const usable = h - 3;

  /* Scale to the 90th percentile rather than the maximum. A single 250 ms
     spike would otherwise flatten a steady 17 ms line into an unreadable
     sliver; bars above the scale are clamped and capped instead, which keeps
     everyday variation legible while still making spikes obvious. */
  const rtts = s.filter(v => v.rtt != null).map(v => v.rtt).sort((a, b) => a - b);
  const p90 = rtts.length ? rtts[Math.floor(0.9 * (rtts.length - 1))] : good;
  const top = Math.max(p90 * 1.4, 1);

  s.slice(-N).forEach((v, i) => {
    const idx = N - Math.min(s.length, N) + i;
    const px = idx * slot + (slot - bw) / 2;
    const band = hzBand(v.rtt, v.loss ?? 0, good, warn);
    if (band === 'down'){
      x.fillStyle = 'rgba(255,59,48,.28)';
      x.fillRect(px, 2, bw, usable);          // full-height ghost = no reply
      x.fillStyle = HZ_COL.down;
      x.fillRect(px, h - 3, bw, 3);
      return;
    }
    const over = v.rtt > top;
    const bh = Math.max(2, Math.min(usable, (v.rtt / top) * usable));
    x.fillStyle = HZ_COL[band];
    x.globalAlpha = band === 'good' ? .85 : .95;
    x.fillRect(px, h - bh, bw, bh);
    x.globalAlpha = 1;
    if (over){                                 // cap marks a clipped spike
      x.fillStyle = HZ_COL.bad;
      x.fillRect(px, 0, bw, 2);
    }
  });
}

/* Choose a column count that fits the container and leaves no stranded tile
   on the last row — a lone tile next to dead space looks broken. */
function hzCols(n, width){
  const fit = Math.max(1, Math.min(4, Math.floor(width / 200)));
  if (n <= fit) return n;                  // everything on one row
  let best = fit, bestGap = Infinity;
  for (let c = fit; c >= 2; c--){
    const gap = (c - (n % c)) % c;         // empty slots on the last row
    if (gap < bestGap){ bestGap = gap; best = c; }   // ties keep more columns
    if (gap === 0) break;
  }
  return best;
}

function hzRender(d){
  HZ.data = d;
  const card = $('hzCard');
  if (!d || d.enabled === false || !(d.targets || []).length){
    card.hidden = true;
    return;
  }
  card.hidden = false;
  $('hzNote').textContent = d.waiting
    ? 'waiting for first probe'
    : `every ${d.interval_s}s · ${d.good_ms}/${d.warn_ms} ms thresholds`;

  const grid = $('hzGrid');
  const cols = hzCols(d.targets.length, grid.clientWidth || 1012);
  grid.style.setProperty('--hz-cols', cols);
  const sig = d.targets.map(t => t.host).join('|');
  if (grid.dataset.sig !== sig){            // rebuild only when targets change
    grid.dataset.sig = sig;
    HZ.cvs.clear();
    grid.innerHTML = d.targets.map((t, i) => `
      <div class="hz-t" data-i="${i}">
        <div class="hz-hd">
          <span class="hz-dot" data-r="dot"></span>
          <span class="hz-nm" data-r="nm"></span>
          <span class="hz-ip" data-r="ip"></span>
        </div>
        <div class="hz-v">
          <span class="hz-ms" data-r="ms">–</span>
          <span class="hz-u">ms</span>
        </div>
        <canvas class="hz-strip" data-r="cv"></canvas>
        <div class="hz-ft" data-r="ft"></div>
      </div>`).join('');
  }

  d.targets.forEach((t, i) => {
    const el = grid.querySelector(`.hz-t[data-i="${i}"]`);
    if (!el) return;
    el.classList.toggle('rowstart', i % cols === 0);
    const q = r => el.querySelector(`[data-r="${r}"]`);
    q('dot').className = 'hz-dot ' + t.state;
    q('nm').textContent = t.label;
    q('ip').textContent = t.host;
    const ms = q('ms');
    ms.className = 'hz-ms ' + t.state;
    ms.textContent = t.state === 'down' ? '—'
      : (t.rtt != null ? t.rtt.toFixed(1) : '–');
    const lossy = (t.loss ?? 0) > 0;
    q('ft').innerHTML = [
      t.min != null ? `<span>min <b>${t.min}</b></span>` : '',
      t.avg != null ? `<span>avg <b>${t.avg}</b></span>` : '',
      `<span class="${lossy ? 'lossy' : ''}">loss <b>${t.loss ?? 0}%</b></span>`,
      t.uptime != null ? `<span>up <b>${t.uptime}%</b></span>` : '',
    ].join('');
    hzDrawStrip(q('cv'), t, d.good_ms, d.warn_ms);
  });
}

async function hzPoll(){
  try{
    const res = await fetch('health.php', {cache:'no-store'});
    hzRender(await res.json());
  }catch(e){ /* dashboard keeps working without it */ }
}

function hzSetLive(on){
  if (on && !HZ.timer){
    hzPoll();
    HZ.timer = setInterval(hzPoll, 5000);
  } else if (!on && HZ.timer){
    clearInterval(HZ.timer); HZ.timer = null;
  }
}

/* redraw on resize so the strips stay sharp */
let hzRz;
window.addEventListener('resize', () => {
  clearTimeout(hzRz);
  hzRz = setTimeout(() => { if (HZ.data) hzRender(HZ.data); tlDraw(); }, 150);
});

/* ================= software update ================= */
const UPD = {info:null, poll:null, dismissed:false};

function updApi(action, extra = {}){
  return fetch('update.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body:JSON.stringify(Object.assign({action}, extra)),
  }).then(async res => {
    const j = await res.json().catch(() => ({error:'bad response'}));
    if (!res.ok) throw new Error(j.error || ('http ' + res.status));
    return j;
  });
}

function updAgo(ts){
  if (!ts) return '';
  const s = Math.max(0, Math.floor(Date.now() / 1000 - ts));
  if (s < 90) return 'just now';
  if (s < 5400) return Math.round(s / 60) + ' min ago';
  if (s < 172800) return Math.round(s / 3600) + ' h ago';
  return Math.round(s / 86400) + ' d ago';
}

function updRender(d){
  UPD.info = d;
  const cur = d.current || '—';
  const latest = d.latest;
  const avail = !!d.update_available;

  if ($('updCur')) {
    $('updCur').textContent = 'v' + cur;
    $('updChecked').textContent = d.checked_at ? 'Checked ' + updAgo(d.checked_at) : '';
    $('updRepo').href = d.repo || '#';
    $('updInstall').hidden = !avail;
    $('updInstall').textContent = avail ? `Install v${latest.version}` : 'Install update';
    $('updState').innerHTML = d.offline
      ? 'Could not reach GitHub — check again when you are back online'
      : (avail
          ? `New version <b>v${esc(latest.version)}</b> available${latest.published_at
              ? ' · released ' + new Date(latest.published_at).toLocaleDateString() : ''}`
          : 'You are running the latest version');
    const notes = avail && latest.notes ? latest.notes.trim() : '';
    $('updNotes').hidden = !notes;
    $('updNotes').textContent = notes;
  }

  const bar = $('updBar');
  if (bar) {
    const show = avail && !UPD.dismissed
      && sessionStorage.getItem('updHide') !== latest.version;
    bar.classList.toggle('on', show);
    if (show)
      $('updBarTxt').innerHTML =
        `Version <b>v${esc(latest.version)}</b> is available — you are on v${esc(cur)}`;
  }

  updRenderStatus(d.status);
}

function updRenderStatus(st){
  const log = $('updLog');
  if (!log) return;
  if (!st){ log.textContent = ''; log.className = 'updlog'; return; }
  const fresh = (Date.now() / 1000 - (st.ts || 0)) < 1800;
  if (st.state === 'running' && fresh){
    log.textContent = st.message || 'Updating…';
    log.className = 'updlog';
    $('updInstall').disabled = true;
    if (!UPD.poll) UPD.poll = setInterval(updPollStatus, 3000);
  } else if (st.state === 'error'){
    log.textContent = 'Update failed: ' + (st.message || 'unknown error');
    log.className = 'updlog err';
    $('updInstall').disabled = false;
    updStopPoll();
  } else if (st.state === 'done'){
    log.textContent = (st.message || 'Updated') + ' — reload the page to load the new UI';
    log.className = 'updlog ok';
    $('updInstall').disabled = false;
    updStopPoll();
  }
}

function updStopPoll(){
  if (UPD.poll){ clearInterval(UPD.poll); UPD.poll = null; }
}

async function updPollStatus(){
  try{
    const d = await updApi('status');
    updRenderStatus(d.status);
    // version changed under us -> the update landed
    if (d.current && UPD.info && d.current !== UPD.info.current) updCheck(false);
  }catch(e){}
}

async function updCheck(force){
  try{
    const d = await updApi('check', force ? {force:true} : {});
    updRender(d);
  }catch(e){
    if ($('updState')) $('updState').textContent = 'Version check failed: ' + e.message;
  }
}

$('updCheck')?.addEventListener('click', async () => {
  $('updState').textContent = 'Checking GitHub…';
  await updCheck(true);
});

$('updInstall')?.addEventListener('click', async () => {
  const v = UPD.info?.latest?.version ?? '';
  if (!confirm(`Install v${v}?\n\nProgram files are replaced and the collectors `
    + `restart. Your database, settings and password are kept.`)) return;
  $('updInstall').disabled = true;
  $('updLog').className = 'updlog';
  $('updLog').textContent = 'Queueing update…';
  try{
    await updApi('apply');
    if (!UPD.poll) UPD.poll = setInterval(updPollStatus, 3000);
    setTimeout(updPollStatus, 1200);
  }catch(e){
    $('updLog').textContent = 'Error: ' + e.message;
    $('updLog').className = 'updlog err';
    $('updInstall').disabled = false;
  }
});

$('updBarGo')?.addEventListener('click', () => {
  document.querySelector('.nav button[data-view="settings"]').click();
  $('updBar').classList.remove('on');
});
$('updBarHide')?.addEventListener('click', () => {
  UPD.dismissed = true;
  if (UPD.info?.latest) sessionStorage.setItem('updHide', UPD.info.latest.version);
  $('updBar').classList.remove('on');
});

/* ================= intro splash ================= */
let splashGone = false;
function hideSplash(){
  if (splashGone) return;
  splashGone = true;
  const sp = $('splash');
  if (!sp) return;
  sp.classList.add('bye');
  setTimeout(() => sp.remove(), 700);
}
setTimeout(hideSplash, 6000);   // never trap the user on the splash

/* ================= data load ================= */
async function load(){
  try{
    const res = await fetch(withDish('api.php?range=' + range), {cache:'no-store'});
    const data = await res.json();
    if (data.dishes) renderDishSel(data.dishes);
    if (data.error){ $('lastRun').textContent = data.error; return; }
    if (data.cfg?.live_poll_s) LIVE_MS = data.cfg.live_poll_s * 1000;
    render(data);
  }catch(e){
    $('lastRun').textContent = 'Offline';
    $('dot').classList.add('err');
    setStatus('bad', 'Offline \u00b7 Stardashy');
  }
  loadSky();
  loadUsage();
  if (activeView === 'energy') loadEnergy();
  hideSplash();
}

/* nav */
document.querySelectorAll('.nav button').forEach(b => {
  b.addEventListener('click', () => {
    document.querySelectorAll('.nav button').forEach(x => x.classList.remove('active'));
    document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
    b.classList.add('active');
    activeView = b.dataset.view;
    $('view-' + activeView).classList.add('active');
    setLive(activeView === 'dish');
    setGeo(activeView === 'sats');
    hzSetLive(activeView === 'dash');
    if (activeView === 'energy') loadEnergy();
    if (activeView === 'debug' || activeView === 'settings') checkAuth();
  });
});

/* 1-7 jump between views; ignored while typing in a field */
document.addEventListener('keydown', e => {
  if (e.metaKey || e.ctrlKey || e.altKey) return;
  const t = e.target.tagName;
  if (t === 'INPUT' || t === 'SELECT' || t === 'TEXTAREA') return;
  const i = '1234567'.indexOf(e.key);
  if (i < 0) return;
  const btn = document.querySelectorAll('.nav button')[i];
  if (btn){ btn.click(); btn.blur(); }
});

/* range tabs (all instances stay in sync) */
document.querySelectorAll('.seg button').forEach(b => {
  b.addEventListener('click', () => {
    range = b.dataset.range;
    document.querySelectorAll('.seg button').forEach(x =>
      x.classList.toggle('active', x.dataset.range === range));
    load();
  });
});

load();
setInterval(() => { if (!document.hidden) load(); }, 60_000);

hzSetLive(true);

updCheck(false);
setInterval(() => { if (!document.hidden) updCheck(false); }, 3600_000);
</script>
</body>
</html>
