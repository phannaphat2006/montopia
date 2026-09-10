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
    const serviceInput = document.getElementById('serviceType');
    const serviceAliases = { web: 'web-application', mobile: 'mobile-application', cloud: 'cloud-infrastructure', web3: 'web3-solutions' };
    document.querySelectorAll('[data-brief-service]').forEach(link => link.addEventListener('click', () => {
        if (!serviceInput) return;
        const original = link.dataset.briefService;
        const managed = serviceAliases[original];
        serviceInput.value = [...serviceInput.options].some(option => option.value === managed) ? managed : original;
    }));

    const form = document.getElementById('contactForm'), review = document.getElementById('formResponse'), status = document.getElementById('formStatus'), reference = document.getElementById('inquiryReference');
    let csrf = '';
    async function csrfToken() { if (csrf) return csrf; const response = await fetch('/api/csrf', { headers: { Accept: 'application/json' }, credentials: 'same-origin' }); if (!response.ok) throw new Error('csrf'); csrf = (await response.json()).token; return csrf; }
    form?.addEventListener('submit', async event => {
        event.preventDefault(); if (!form.reportValidity()) return; review.hidden = true; status.textContent = 'กำลังบันทึกข้อมูล…'; const button = form.querySelector('button[type=submit]'); button.disabled = true;
        const data = new FormData(form), services = { web: 'Web Application / ระบบองค์กร', mobile: 'Mobile Application', cloud: 'Cloud & Infrastructure', web3: 'Web3 / NFT', other: 'ต้องการคำแนะนำ' };
        const read = key => String(data.get(key) || '').trim();
        const selectedService = serviceInput?.selectedOptions[0]?.textContent?.trim() || services[read('service')] || services.other;
        const payload = { client_name: read('company') || read('name'), client_email: read('email'), client_phone: read('phone'), budget_range: read('budget'), project_scope: [`ผู้ติดต่อ: ${read('name')}`, `บริการ: ${selectedService}`, `ช่วงเวลา: ${read('timeline')}`, '', read('message')].join('\n') };
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

    async function fetchData(url) {
        const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!response.ok) throw new Error('โหลดข้อมูลไม่สำเร็จ');
        return (await response.json()).data;
    }

    async function loadCompany() {
        try {
            const company = await fetchData('/api/company');
            if (!company) return;
            document.querySelectorAll('[data-company-name]').forEach(node => { node.textContent = company.name; });
            document.querySelectorAll('[data-company-description]').forEach(node => { node.textContent = company.description; });
            document.querySelectorAll('[data-company-address]').forEach(node => { node.textContent = company.address; });
            document.querySelectorAll('[data-company-registration]').forEach(node => { node.textContent = company.registration_number ? `เลขทะเบียนนิติบุคคล ${company.registration_number}` : ''; });
            document.querySelectorAll('[data-company-email]').forEach(node => { node.textContent = company.email; node.href = `mailto:${company.email}`; });
        } catch { }
    }

    async function loadManagedServices() {
        const workspace = document.querySelector('.services-workspace');
        const directory = document.querySelector('.directory-grid');
        if (!workspace) return;
        try {
            const items = await fetchData('/api/services');
            if (!items.length) return;

            const tabList = document.createElement('div'); tabList.className = 'service-tabs'; tabList.role = 'tablist'; tabList.ariaLabel = 'เลือกบริการ';
            const managedTabs = [];
            const managedPanels = [];
            items.forEach((item, index) => {
                const tab = document.createElement('button'); tab.type = 'button'; tab.role = 'tab'; tab.id = `managed-tab-${item.slug}`; tab.dataset.managedService = item.slug; tab.setAttribute('aria-controls', `managed-panel-${item.slug}`); tab.append(document.createTextNode(item.title));
                const number = document.createElement('span'); number.textContent = String(index + 1).padStart(2, '0'); tab.append(number);

                const panel = document.createElement('article'); panel.className = 'service-panel'; panel.role = 'tabpanel'; panel.id = `managed-panel-${item.slug}`; panel.setAttribute('aria-labelledby', tab.id); panel.tabIndex = 0;
                const heading = document.createElement('div'); heading.className = 'panel-title';
                const label = document.createElement('span'); label.textContent = item.icon_label || 'SERVICE';
                const title = document.createElement('h3'); title.textContent = item.short_description;
                heading.append(label, title);
                const description = document.createElement('p'); description.textContent = item.description;
                const link = document.createElement('a'); link.href = '#contact'; link.className = 'text-link'; link.textContent = 'ปรึกษาบริการนี้ ↗';
                link.addEventListener('click', () => { if (serviceInput) serviceInput.value = item.slug; });
                panel.append(heading, description, link);
                managedTabs.push(tab); managedPanels.push(panel); tabList.append(tab);
            });

            const chooseManagedService = (slug, focus = false) => {
                managedTabs.forEach((tab, index) => {
                    const selected = tab.dataset.managedService === slug;
                    tab.setAttribute('aria-selected', String(selected)); tab.tabIndex = selected ? 0 : -1; managedPanels[index].hidden = !selected;
                    if (selected && focus) tab.focus();
                });
            };
            managedTabs.forEach((tab, index) => {
                tab.addEventListener('click', () => chooseManagedService(tab.dataset.managedService));
                tab.addEventListener('keydown', event => {
                    let next;
                    if (['ArrowRight', 'ArrowDown'].includes(event.key)) next = (index + 1) % managedTabs.length;
                    if (['ArrowLeft', 'ArrowUp'].includes(event.key)) next = (index - 1 + managedTabs.length) % managedTabs.length;
                    if (next !== undefined) { event.preventDefault(); chooseManagedService(managedTabs[next].dataset.managedService, true); }
                });
            });
            workspace.replaceChildren(tabList, ...managedPanels);
            chooseManagedService(items[0].slug);

            if (directory) {
                directory.replaceChildren(...items.map((item, index) => {
                    const link = document.createElement('a'); link.href = '#services';
                    const number = document.createElement('span'); number.textContent = String(index + 1).padStart(2, '0');
                    const title = document.createElement('strong'); title.textContent = item.title;
                    const summary = document.createElement('small'); summary.textContent = item.short_description;
                    const arrow = document.createElement('b'); arrow.ariaHidden = 'true'; arrow.textContent = '↗';
                    link.append(number, title, summary, arrow); link.addEventListener('click', () => chooseManagedService(item.slug)); return link;
                }));
            }
            if (serviceInput) {
                const first = serviceInput.options[0];
                serviceInput.replaceChildren(first, ...items.map(item => {
                    const option = document.createElement('option'); option.value = item.slug; option.textContent = item.title; return option;
                }));
            }
        } catch { }
    }

    async function loadPackages() {
        const section = document.getElementById('packages');
        const grid = document.getElementById('packageGrid');
        if (!section || !grid) return;
        try {
            const items = await fetchData('/api/service-packages');
            if (!items.length) return;
            grid.replaceChildren(...items.map(item => {
                const meta = document.createElement('span'); meta.className = 'package-meta'; meta.textContent = item.service?.title || 'Custom Software';
                const name = document.createElement('h3'); name.textContent = item.name;
                const price = document.createElement('strong'); price.className = 'package-price'; price.textContent = item.price_label;
                const description = document.createElement('p'); description.textContent = item.description;
                const features = document.createElement('ul');
                String(item.features || '').split('\n').map(value => value.trim()).filter(Boolean).forEach(value => { const li = document.createElement('li'); li.textContent = value; features.append(li); });
                const footer = document.createElement('div'); footer.className = 'package-footer';
                const time = document.createElement('span'); time.textContent = item.delivery_time || 'กำหนดเวลาหลังสรุปขอบเขต';
                const link = document.createElement('a'); link.href = '#contact'; link.textContent = 'สอบถามแพ็กเกจ ↗';
                link.addEventListener('click', () => { if (serviceInput && item.service?.slug) serviceInput.value = item.service.slug; });
                footer.append(time, link);
                const card = document.createElement('article'); card.className = item.is_featured ? 'featured' : ''; card.append(meta, name, price, description, features, footer); return card;
            }));
            section.hidden = false;
        } catch { }
    }

    async function loadArticles() {
        const section = document.getElementById('insights');
        const grid = document.getElementById('articleGrid');
        if (!section || !grid) return;
        try {
            const items = await fetchData('/api/articles');
            if (!items.length) return;
            grid.replaceChildren(...items.slice(0, 6).map(item => {
                const image = item.image_url ? document.createElement('img') : null;
                if (image) { image.src = item.image_url; image.alt = `ภาพประกอบบทความ ${item.title}`; image.loading = 'lazy'; image.decoding = 'async'; }
                const date = document.createElement('time'); date.dateTime = item.published_at || item.created_at; date.textContent = new Intl.DateTimeFormat('th-TH', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(date.dateTime));
                const title = document.createElement('h3'); title.textContent = item.title;
                const excerpt = document.createElement('p'); excerpt.textContent = item.excerpt;
                const details = document.createElement('details');
                const summary = document.createElement('summary'); summary.textContent = 'อ่านบทความ';
                const content = document.createElement('p'); content.className = 'article-content'; content.textContent = item.content;
                details.append(summary, content);
                const card = document.createElement('article'); if (image) card.append(image); card.append(date, title, excerpt, details); return card;
            }));
            section.hidden = false;
        } catch { }
    }

    loadCompany();
    loadManagedServices();
    loadPackages();
    loadArticles();
});
