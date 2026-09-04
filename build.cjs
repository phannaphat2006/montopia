const fs = require('node:fs');
const path = require('node:path');
const esbuild = require('esbuild');
const root = __dirname;
const publicFiles = ['index.html', 'operations.html', 'style.css', 'app.js', 'logo.jpg', 'project-showcase-v2.png', 'bullmoon-launch.webp', 'bullmoon-event.webp', 'og.png', 'portal.html', 'portal-demo.html', 'portal.css', 'brief.html'];
const local = path.join(root,'.env.local');
const vars = fs.existsSync(local) ? Object.fromEntries(fs.readFileSync(local,'utf8').split(/\r?\n/).filter(line=>/^PORTAL_[A-Z_]+=/.test(line)).map(line=>{const index=line.indexOf('=');return [line.slice(0,index),line.slice(index+1).trim().replace(/^(?:"(.*)"|'(.*)')$/,'$1$2')];})) : {};
const get = key => process.env[key] ?? vars[key] ?? '';
const config = {enabled:get('PORTAL_ENABLED')==='true',supabaseUrl:get('PORTAL_SUPABASE_URL'),publishableKey:get('PORTAL_SUPABASE_PUBLISHABLE_KEY')};
if(config.publishableKey && !/^sb_publishable_[A-Za-z0-9_-]+$/.test(config.publishableKey)) throw new Error('Only a Supabase publishable key is accepted. Never use secret/service_role keys.');
if(config.supabaseUrl && !/^https:\/\/[a-z0-9-]+\.supabase\.co\/?$/.test(config.supabaseUrl)) throw new Error('Use the HTTPS Supabase project URL.');
if(config.enabled && (!config.supabaseUrl||!config.publishableKey)) throw new Error('Enabled portal requires public URL and publishable key.');
fs.mkdirSync(path.join(root, 'dist'), { recursive: true });
for (const file of publicFiles) {
  fs.copyFileSync(path.join(root, 'montopia', file), path.join(root, 'dist', file));
}
// Same generated assets serve the existing local preview and the static deployment.
for(const target of ['montopia','dist']) {
  esbuild.buildSync({entryPoints:{portal:path.join(root,'src/portal/portal.js'),brief:path.join(root,'src/brief.js')},bundle:true,format:'esm',target:'es2022',minify:true,outdir:path.join(root,target),logLevel:'warning'});
  fs.writeFileSync(path.join(root,target,'portal-config.js'),'window.MONSTOPIA_PORTAL = Object.freeze('+JSON.stringify(config)+');\n');
}
console.log(`Built ${publicFiles.length} public files into dist.`);
