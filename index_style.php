<style>
:root{
  --bg:#02030a;
  --bg2:#050816;
  --card:rgba(11,15,31,.82);
  --card-strong:#0b1020;
  --text:#f5f7ff;
  --muted:#a8b1c7;
  --accent:#16c784;
  --accent2:#0ea66d;
  --blue:#4d7cff;
  --soft:rgba(22,199,132,.11);
  --soft-blue:rgba(77,124,255,.12);
  --border:rgba(255,255,255,.10);
  --border-strong:rgba(255,255,255,.17);
  --shadow:0 22px 70px rgba(0,0,0,.42);
  --r:20px;
}

*{box-sizing:border-box;margin:0;padding:0}
html{
  scroll-behavior:smooth;
  scrollbar-width:none;
  -ms-overflow-style:none;
  background:#02030a;
}
html::-webkit-scrollbar,
body::-webkit-scrollbar,
.modal::-webkit-scrollbar,
.mc::-webkit-scrollbar{display:none;width:0;height:0}

body{
  min-height:100vh;
  font-family:Segoe UI,system-ui,-apple-system,BlinkMacSystemFont,Roboto,sans-serif;
  background:
    radial-gradient(circle at 15% 20%, rgba(63,28,120,.28), transparent 32%),
    radial-gradient(circle at 85% 15%, rgba(0,72,120,.22), transparent 30%),
    radial-gradient(circle at 70% 80%, rgba(0,118,82,.15), transparent 30%),
    #02030a;
  color:var(--text);
  line-height:1.6;
  overflow-x:hidden;
  scrollbar-width:none;
  -ms-overflow-style:none;
  position:relative;
}
body::-webkit-scrollbar{display:none}

body::before{
  content:"";
  position:fixed;
  inset:0;
  z-index:-2;
  pointer-events:none;
  background:
    radial-gradient(circle at 20% 80%, #1a0033 0%, transparent 50%),
    radial-gradient(circle at 80% 20%, #000033 0%, transparent 50%),
    url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 100 100'><circle cx='10' cy='10' r='1' fill='white'/><circle cx='30' cy='70' r='1.5' fill='white'/><circle cx='70' cy='30' r='1' fill='white'/><circle cx='90' cy='80' r='1.2' fill='white'/><circle cx='50' cy='50' r='1.8' fill='white'/></svg>") repeat;
  background-size:cover,cover,120px 120px;
  opacity:.48;
}

body::after{
  content:"";
  position:fixed;
  inset:0;
  z-index:-1;
  pointer-events:none;
  background:
    radial-gradient(circle at 50% 0%, rgba(255,255,255,.04), transparent 36%),
    linear-gradient(180deg,rgba(2,3,10,.12),rgba(2,3,10,.76));
}

html.modal-open,
body.modal-open{
  overflow:hidden !important;
  scrollbar-width:none !important;
  -ms-overflow-style:none !important;
}
body.modal-open::-webkit-scrollbar{display:none !important}

a{text-decoration:none;color:inherit}
button,input,select{font:inherit}
button{touch-action:manipulation}
input,select{
  font-size:16px !important;
  touch-action:manipulation;
}

.wrap{max-width:1180px;margin:auto;padding:0 20px}

.nav{
  position:sticky;
  top:0;
  z-index:50;
  background:rgba(2,3,10,.78);
  backdrop-filter:blur(16px);
  border-bottom:1px solid var(--border);
}
.navin{height:68px;display:flex;align-items:center;justify-content:space-between}
.logo{display:flex;align-items:center;gap:10px;font-weight:900}
.logo i{
  display:grid;place-items:center;width:36px;height:36px;border-radius:11px;
  background:var(--accent);color:#03110b;font-style:normal
}
.navlinks{display:flex;gap:25px;color:var(--muted);font-size:.9rem;font-weight:700}
.navlinks a:hover{color:var(--accent)}
.actions{display:flex;gap:9px}

.btn{
  border:0;border-radius:12px;padding:11px 18px;font-weight:800;cursor:pointer;
  display:inline-flex;align-items:center;justify-content:center;gap:8px;
  transition:transform .18s,opacity .18s,background .18s,border-color .18s;
}
.btn:hover{transform:translateY(-1px)}
.primary{background:var(--accent);color:#03110b}
.primary:hover{background:#22d998}
.ghost{background:rgba(255,255,255,.035);border:1px solid var(--border-strong);color:var(--text)}
.ghost:hover{background:rgba(255,255,255,.07)}

.hero{
  background:
    radial-gradient(circle at 75% 30%,rgba(22,199,132,.18),transparent 35%),
    radial-gradient(circle at 20% 70%,rgba(77,124,255,.14),transparent 32%);
  color:var(--text);
  padding:82px 0 90px;
  overflow:hidden;
}
.heroGrid{display:grid;grid-template-columns:1.15fr .85fr;gap:45px;align-items:center}
.badge{
  display:inline-block;background:rgba(255,255,255,.07);border:1px solid var(--border-strong);
  padding:7px 14px;border-radius:99px;font-size:.76rem;font-weight:900;letter-spacing:.5px;margin-bottom:18px
}
h1{font-size:clamp(2.3rem,5vw,4rem);line-height:1.05;letter-spacing:-1.8px;margin-bottom:18px}
.hero p{max-width:650px;color:#d9deea;font-size:1.05rem;margin-bottom:28px}
.heroBtns{display:flex;gap:12px;flex-wrap:wrap}
.hero .primary{background:#fff;color:#07100c}
.hero .ghost{color:#fff;border-color:rgba(255,255,255,.25)}
.heroCard{
  background:rgba(255,255,255,.055);border:1px solid var(--border-strong);padding:25px;
  border-radius:20px;backdrop-filter:blur(10px);box-shadow:var(--shadow)
}
.heroCard h3{margin-bottom:15px}
.mini{
  display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid rgba(255,255,255,.12);
  font-size:.9rem;color:#d7dceb
}
.mini b{color:#fff}.mini:last-child{border:0}

section{padding:78px 0}
.alt{background:rgba(255,255,255,.025);border-top:1px solid rgba(255,255,255,.035);border-bottom:1px solid rgba(255,255,255,.035)}
.head{text-align:center;max-width:760px;margin:0 auto 42px}
.tag{color:var(--accent);font-size:.76rem;font-weight:900;text-transform:uppercase;letter-spacing:1px}
.head h2{font-size:2rem;line-height:1.15;margin:7px 0 10px}
.head p{color:var(--muted)}

.roleGrid{display:grid;grid-template-columns:1fr 1fr;gap:22px}
.roleCard{
  background:var(--card);border:1px solid var(--border);border-radius:22px;padding:30px;
  box-shadow:var(--shadow);position:relative;overflow:hidden;backdrop-filter:blur(8px)
}
.roleCard.dev{border-top:4px solid var(--accent)}
.roleCard.inv{border-top:4px solid var(--blue)}
.roleIcon{
  width:50px;height:50px;border-radius:15px;background:var(--soft);display:grid;
  place-items:center;color:var(--accent);font-size:1.25rem;margin-bottom:17px
}
.inv .roleIcon{background:var(--soft-blue);color:var(--blue)}
.roleCard h3{font-size:1.35rem;margin-bottom:8px}
.roleCard p{color:var(--muted);font-size:.92rem}
.list{margin:18px 0;display:grid;gap:10px}
.list div{display:flex;gap:9px;font-size:.88rem;color:#d9deea}
.list i{color:var(--accent);margin-top:4px}

.steps{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.box{
  background:var(--card);border:1px solid var(--border);border-radius:18px;padding:23px;
  box-shadow:0 12px 40px rgba(0,0,0,.22)
}
.num{color:var(--accent);font-weight:900;font-size:.8rem}
.box h3{margin:7px 0}.box p{color:var(--muted);font-size:.86rem}

.featureGrid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.feature{
  background:var(--card);border:1px solid var(--border);border-radius:18px;padding:24px;
  box-shadow:0 12px 40px rgba(0,0,0,.18)
}
.feature i{color:var(--accent);font-size:1.25rem;margin-bottom:12px}
.feature p{color:var(--muted);font-size:.88rem}

.markets-section{padding-top:30px}
.market-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px}
.market-card{
  background:var(--card);border:1px solid var(--border);border-radius:17px;padding:20px;
  display:flex;align-items:center;gap:13px;min-height:105px
}
.market-card i{color:var(--accent);font-size:1.25rem}
.market-card strong{display:block;font-size:.92rem}.market-card span{display:block;color:var(--muted);font-size:.76rem;margin-top:2px}

.cta{
  background:
    radial-gradient(circle at 50% 0%,rgba(22,199,132,.16),transparent 48%),
    #06100d;
  color:white;text-align:center;padding:70px 20px;border-top:1px solid var(--border)
}
.cta h2{font-size:2.2rem}.cta p{color:#b9c2d4;max-width:650px;margin:10px auto 23px}
footer{padding:25px;text-align:center;color:var(--muted);font-size:.78rem}

.modal{
  position:fixed;inset:0;background:rgba(0,0,0,.72);display:none;align-items:center;justify-content:center;
  padding:20px;z-index:100;backdrop-filter:blur(8px);overscroll-behavior:contain
}
.modal.open{display:flex}
.mc{
  background:#080d1b;color:var(--text);width:min(520px,100%);max-height:90vh;overflow-y:auto;
  border:1px solid var(--border-strong);border-radius:22px;padding:28px;
  box-shadow:0 25px 80px rgba(0,0,0,.65);overscroll-behavior:contain;
  scrollbar-width:none;-ms-overflow-style:none
}
.mc::-webkit-scrollbar{display:none}
.mc h3{font-size:1.35rem;margin-bottom:5px}
.sub{color:var(--muted);font-size:.87rem;margin-bottom:18px}

.field{margin-bottom:14px}
.field label{
  display:block;font-size:.73rem;text-transform:uppercase;letter-spacing:.5px;
  font-weight:800;color:var(--muted);margin-bottom:6px
}
.field input,.field select{
  width:100%;padding:12px 13px;border:1px solid var(--border-strong);border-radius:12px;
  background:#050916;color:var(--text);font:inherit;outline:none
}
.field input:focus,.field select:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(22,199,132,.08)}
.roleChoice{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:18px}
.roleChoice label{
  border:1px solid var(--border);border-radius:14px;padding:13px;cursor:pointer;background:rgba(255,255,255,.025)
}
.roleChoice input{margin-right:6px}
.roleChoice label:has(input:checked){border-color:var(--accent);background:var(--soft)}
.err{
  display:none;background:rgba(224,75,75,.11);color:#ff8d8d;
  padding:10px 12px;border-radius:10px;font-size:.84rem;margin-bottom:14px
}
.err.show{display:block}
.notice{background:var(--soft);padding:12px;border-radius:12px;color:#dfe9e4;font-size:.82rem;margin-bottom:15px}
.check{font-size:.8rem;color:var(--muted);display:flex;gap:8px;margin:10px 0 18px}
.switch{text-align:center;color:var(--muted);font-size:.83rem;margin-top:15px}
.switch a{color:var(--accent);font-weight:800;cursor:pointer}

.adGrid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.ad{
  background:var(--card);border:1px solid var(--border);border-radius:20px;padding:27px;
  box-shadow:var(--shadow)
}
.ad h3{font-size:1.3rem;margin-bottom:8px}.ad p{color:var(--muted);font-size:.9rem}
.ad ul{padding-left:20px;color:var(--muted);font-size:.86rem;margin:15px 0}
.ad li{margin:7px 0}

@media(max-width:1000px){
  .market-grid{grid-template-columns:repeat(3,1fr)}
}
@media(max-width:850px){
  .heroGrid,.roleGrid,.adGrid{grid-template-columns:1fr}
  .steps{grid-template-columns:1fr 1fr}
  .featureGrid{grid-template-columns:1fr}
  .navlinks{display:none}
}
@media(max-width:600px){
  .market-grid{grid-template-columns:1fr 1fr}
}
@media(max-width:520px){
  .steps{grid-template-columns:1fr}
  .actions .btn{padding:9px 11px;font-size:.8rem}
  .hero{padding:60px 0}
  .mc{padding:22px;max-height:88vh}
  .roleChoice{grid-template-columns:1fr}
  .market-grid{grid-template-columns:1fr}
}

/* Prevent accidental page movement while a modal is open. */
.modal.open ~ *{pointer-events:auto}

</style>
