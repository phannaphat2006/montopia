document.addEventListener('DOMContentLoaded', () => {
    const menuButton = document.getElementById('mobileToggle'), menu = document.getElementById('mainNav');
    const closeMenu = () => { menu?.classList.remove('is-open'); menuButton?.setAttribute('aria-expanded', 'false'); };
    menuButton?.addEventListener('click', () => { const open = menu.classList.toggle('is-open'); menuButton.setAttribute('aria-expanded', String(open)); });
    menu?.querySelectorAll('a').forEach(link => link.addEventListener('click', closeMenu));
    document.addEventListener('keydown', event => { if (event.key === 'Escape') { closeMenu(); menuButton?.focus(); } });
    document.addEventListener('click', event => { if (!event.target.closest('.navbar')) closeMenu(); });
    window.matchMedia('(min-width: 901px)').addEventListener('change', closeMenu);

    const tabs = [...document.querySelectorAll('[data-service]')];
    function chooseService(key, focus = false) { tabs.forEach(tab => { const active = tab.dataset.service === key; tab.setAttribute('aria-selected', String(active)); tab.tabIndex = active ? 0 : -1; const panel = document.getElementById(tab.getAttribute('aria-controls')); if (panel) panel.hidden = !active; if (active && focus) tab.focus(); }); }
    tabs.forEach((tab, index) => { tab.addEventListener('click', () => chooseService(tab.dataset.service)); tab.addEventListener('keydown', event => { let next; if (['ArrowRight', 'ArrowDown'].includes(event.key)) next = (index + 1) % tabs.length; if (['ArrowLeft', 'ArrowUp'].includes(event.key)) next = (index - 1 + tabs.length) % tabs.length; if (event.key === 'Home') next = 0; if (event.key === 'End') next = tabs.length - 1; if (next !== undefined) { event.preventDefault(); chooseService(tabs[next].dataset.service, true); } }); });
    document.querySelectorAll('[data-service-link]').forEach(link => link.addEventListener('click', () => chooseService(link.dataset.serviceLink)));
    const serviceInput = document.getElementById('serviceType'); document.querySelectorAll('[data-brief-service]').forEach(link => link.addEventListener('click', () => { if (serviceInput) serviceInput.value = link.dataset.briefService; }));

    const form = document.getElementById('contactForm'), review = document.getElementById('formResponse'), status = document.getElementById('formStatus'), reference = document.getElementById('inquiryReference');
    let csrf = '';
    async function csrfToken() { if (csrf) return csrf; const response = await fetch('/api/csrf', { headers: { Accept: 'application/json' }, credentials: 'same-origin' }); if (!response.ok) throw new Error('csrf'); csrf = (await response.json()).token; return csrf; }
    form?.addEventListener('submit', async event => {
        event.preventDefault(); if (!form.reportValidity()) return; review.hidden = true; status.textContent = 'กำลังบันทึกข้อมูล…'; const button = form.querySelector('button[type=submit]'); button.disabled = true;
        const data = new FormData(form), services = { web: 'Web Application / ระบบองค์กร', mobile: 'Mobile Application', cloud: 'Cloud & Infrastructure', web3: 'Web3 / NFT', other: 'ต้องการคำแนะนำ' };
        const read = key => String(data.get(key) || '').trim();
        const payload = { client_name: read('company') || read('name'), client_email: read('email'), client_phone: read('phone'), budget_range: read('budget'), project_scope: [`ผู้ติดต่อ: ${read('name')}`, `บริการ: ${services[read('service')] || services.other}`, `ช่วงเวลา: ${read('timeline')}`, '', read('message')].join('\n') };
        try { const response = await fetch('/api/inquiries', { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': await csrfToken() }, body: JSON.stringify(payload) }); const body = await response.json(); if (!response.ok) { const first = Object.values(body.errors || {})[0]?.[0]; throw new Error(first || body.message || 'ส่งข้อมูลไม่สำเร็จ'); } reference.textContent = body.reference; review.hidden = false; status.textContent = 'บันทึกข้อมูลเรียบร้อย ทีมงานสามารถติดตามรายการนี้ในระบบหลังบ้านได้แล้ว'; form.reset(); review.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'nearest' }); } catch (error) { status.textContent = error.message === 'csrf' ? 'เชื่อมต่อระบบไม่ได้ กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง' : error.message; } finally { button.disabled = false; }
    });

    const portfolioLibrary = document.getElementById('portfolioLibrary'), portfolioGrid = document.getElementById('portfolioGrid');
    async function loadPortfolios() {
        if (!portfolioLibrary || !portfolioGrid) return;
        try {
            const response = await fetch('/api/portfolios', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) return;
            const items = (await response.json()).data || [];
            if (!items.length) return;
            portfolioGrid.replaceChildren(...items.map(item => {
                const image = document.createElement('img'); image.src = item.image_url; image.alt = `ภาพหน้าจอผลงาน ${item.title}`; image.loading = 'lazy'; image.decoding = 'async';
                const label = document.createElement('span'); label.className = 'portfolio-card-label'; label.textContent = item.category;
                const title = document.createElement('h4'); title.textContent = item.title;
                const description = document.createElement('p'); description.textContent = item.description;
                const technologies = document.createElement('div'); technologies.className = 'portfolio-tech';
                String(item.technologies || '').split(',').map(value => value.trim()).filter(Boolean).forEach(value => { const tag = document.createElement('span'); tag.textContent = value; technologies.append(tag); });
                const body = document.createElement('div'); body.className = 'portfolio-card-body'; body.append(label, title, description, technologies);
                const card = document.createElement('article'); card.className = 'portfolio-card'; card.append(image, body); return card;
            }));
            portfolioLibrary.hidden = false;
        } catch { }
    }
    loadPortfolios();
});
