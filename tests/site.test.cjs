const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.join(__dirname, '..', 'montopia');
const pages = ['index.html', 'operations.html'];
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const ids = html => [...html.matchAll(/\bid="([^"]+)"/g)].map(m => m[1]);
for (const page of pages) {
  const html = read(page);
  assert.equal(new Set(ids(html)).size, ids(html).length, `${page}: duplicate IDs`);
  assert.equal((html.match(/<h1[ >]/g) || []).length, 1, `${page}: one H1`);
  assert(!/admin1234|loginScreen|99\.9%|24\/7/.test(html));
  for (const [, href] of html.matchAll(/(?:href|src)="([^"]+)"/g)) {
    if (/^(https?:|mailto:)/.test(href)) continue;
    const [file, anchor] = href.split('#');
    const target = file || page;
    assert(fs.existsSync(path.join(root, target)), `${page}: missing ${target}`);
    if (anchor && target.endsWith('.html')) assert(ids(read(target)).includes(anchor), `Missing ${target}#${anchor}`);
  }
  for (const [, target] of html.matchAll(/<label[^>]+for="([^"]+)"/g)) assert(ids(html).includes(target));
  assert(html.includes('og:image') && html.includes('twitter:image'));
}
const html = read('index.html');
assert(html.includes('ภาพจำลองแนวคิด ไม่ใช่หน้าจอผลงานลูกค้าจริง'));
assert(!html.includes('ตอบกลับภายใน 1–2'));
const css = read('style.css');
assert.equal((css.match(/\{/g) || []).length, (css.match(/\}/g) || []).length);
for (const [, file] of css.matchAll(/url\(([^)]+)\)/g)) assert(fs.existsSync(path.join(root, file)));

class Element {
  constructor(id = '') { this.id = id; this.events = {}; this.attrs = {}; this.dataset = {}; this.value = ''; this.hidden = false; this.classes = new Set(); this.classList = { toggle: name => { if (this.classes.has(name)) { this.classes.delete(name); return false; } this.classes.add(name); return true; }, remove: name => this.classes.delete(name), contains: name => this.classes.has(name) }; }
  addEventListener(type, cb) { (this.events[type] ||= []).push(cb); }
  fire(type, data = {}) { return Promise.all((this.events[type] || []).map(cb => cb({ preventDefault() {}, target: this, ...data }))); }
  setAttribute(k, v) { this.attrs[k] = v; }
  getAttribute(k) { return this.attrs[k]; }
  querySelectorAll() { return []; }
  focus() { this.focused = true; }
  scrollIntoView() {}
  select() { this.selected = true; }
  closest() { return true; }
  reportValidity() { return this.valid !== false; }
}
async function testInteractions() {
  const elements = Object.fromEntries(ids(html).map(id => [id, new Element(id)]));
  const tabs = ['web', 'mobile', 'cloud', 'web3'].map(key => { const tab = elements['tab-' + key]; tab.dataset.service = key; tab.attrs['aria-controls'] = 'panel-' + key; return tab; });
  const directoryLink = new Element(); directoryLink.dataset.serviceLink = 'mobile';
  const briefLink = new Element(); briefLink.dataset.briefService = 'cloud';
  elements.serviceType.dispatchEvent = event => elements.contactForm.fire(event.type);
  const doc = new Element();
  doc.getElementById = id => elements[id] || null;
  doc.querySelectorAll = selector => selector === '[data-service]' ? tabs : selector === '[data-service-link]' ? [directoryLink] : selector === '[data-brief-service]' ? [briefLink] : [];
  const fields = { name:'userName', company:'company', email:'userEmail', phone:'userPhone', service:'serviceType', budget:'budget', timeline:'timeline', message:'userMessage' };
  const matchMedia = () => ({ matches: true, addEventListener() {} });
  let clipboard = '';
  const context = { document:doc, window:{ matchMedia }, matchMedia, Event:class { constructor(type) { this.type = type; } }, FormData:class { get(key) { return elements[fields[key]]?.value; } }, navigator:{ clipboard:{ writeText:async text => { clipboard = text; } } } };
  vm.runInNewContext(read('app.js'), context);
  await doc.fire('DOMContentLoaded');
  await elements.mobileToggle.fire('click');
  assert.equal(elements.mobileToggle.attrs['aria-expanded'], 'true');
  await doc.fire('keydown', { key:'Escape' });
  assert.equal(elements.mobileToggle.attrs['aria-expanded'], 'false');
  await directoryLink.fire('click');
  assert.equal(elements['panel-mobile'].hidden, false);
  assert.equal(elements['panel-web'].hidden, true);
  await tabs[1].fire('keydown', { key:'End' });
  assert.equal(tabs[3].attrs['aria-selected'], 'true');
  await tabs[3].fire('keydown', { key:'ArrowRight' });
  assert.equal(tabs[0].attrs['aria-selected'], 'true');
  await briefLink.fire('click');
  assert.equal(elements.serviceType.value, 'cloud');
  Object.entries({ userName:'  ผู้ทดสอบ  ', company:'บริษัทตัวอย่าง', userEmail:'test@example.com', userPhone:'0810000000', budget:'ยังไม่กำหนด', timeline:'ภายใน 1 เดือน', userMessage:'<img src=x onerror=alert(1)> & ทดสอบข้อมูล' }).forEach(([id, value]) => elements[id].value = value);
  await elements.contactForm.fire('submit');
  assert.equal(elements.formResponse.hidden, false);
  assert(elements.emailDraft.href.startsWith('mailto:contact@monstopia.co.th?'));
  assert(decodeURIComponent(elements.emailDraft.href).includes('บริษัทตัวอย่าง'));
  assert.equal(elements.userName.value, 'ผู้ทดสอบ');
  assert(elements.briefPreview.value.includes('<img src=x onerror=alert(1)>'));
  assert.equal(elements.formResponse.innerHTML, undefined, 'User input must never become HTML');
  await elements.copyBrief.fire('click');
  assert.equal(clipboard, elements.briefPreview.value);
  await elements.contactForm.fire('input');
  assert.equal(elements.formResponse.hidden, true);
  elements.contactForm.valid = false;
  await elements.contactForm.fire('submit');
  assert.equal(elements.formResponse.hidden, true);
  context.navigator.clipboard.writeText = async () => { throw new Error('Unavailable'); };
  await elements.copyBrief.fire('click');
  assert.equal(elements.briefPreview.selected, true);
  const empty = new Element(); empty.querySelectorAll = () => []; empty.getElementById = () => null;
  vm.runInNewContext(read('app.js'), { ...context, document:empty });
  await empty.fire('DOMContentLoaded');
  console.log('PASS: page links, IDs, assets, metadata, tabs, keyboard navigation, mobile menu, service selection, form validation, email encoding, copy and fallback, operations page initialization.');
}
testInteractions().catch(error => { console.error(error); process.exitCode = 1; });
