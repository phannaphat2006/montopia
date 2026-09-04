document.addEventListener("DOMContentLoaded", () => {
  const menuButton = document.getElementById("mobileToggle");
  const menu = document.getElementById("mainNav");
  function closeMenu() {
    if (!menuButton || !menu) return;
    menu.classList.remove("is-open");
    menuButton.setAttribute("aria-expanded", "false");
  }
  menuButton?.addEventListener("click", () => {
    const open = menu.classList.toggle("is-open");
    menuButton.setAttribute("aria-expanded", String(open));
  });
  menu?.querySelectorAll("a").forEach(link => link.addEventListener("click", closeMenu));
  document.addEventListener("keydown", event => {
    if (event.key === "Escape" && menu?.classList.contains("is-open")) {
      closeMenu();
      menuButton.focus();
    }
  });
  document.addEventListener("click", event => {
    if (!event.target.closest(".navbar")) closeMenu();
  });
  window.matchMedia("(min-width: 901px)").addEventListener("change", closeMenu);

  const tabs = [...document.querySelectorAll("[data-service]")];
  function chooseService(key, focus = false) {
    tabs.forEach(tab => {
      const active = tab.dataset.service === key;
      tab.setAttribute("aria-selected", String(active));
      tab.tabIndex = active ? 0 : -1;
      document.getElementById(tab.getAttribute("aria-controls")).hidden = !active;
      if (active && focus) tab.focus();
    });
  }
  tabs.forEach((tab, index) => {
    tab.addEventListener("click", () => chooseService(tab.dataset.service));
    tab.addEventListener("keydown", event => {
      let next;
      if (["ArrowRight", "ArrowDown"].includes(event.key)) next = (index + 1) % tabs.length;
      if (["ArrowLeft", "ArrowUp"].includes(event.key)) next = (index - 1 + tabs.length) % tabs.length;
      if (event.key === "Home") next = 0;
      if (event.key === "End") next = tabs.length - 1;
      if (next !== undefined) {
        event.preventDefault();
        chooseService(tabs[next].dataset.service, true);
      }
    });
  });
  document.querySelectorAll("[data-service-link]").forEach(link => {
    link.addEventListener("click", () => chooseService(link.dataset.serviceLink));
  });
  const serviceInput = document.getElementById("serviceType");
  document.querySelectorAll("[data-brief-service]").forEach(link => {
    link.addEventListener("click", () => {
      if (serviceInput) {
        serviceInput.value = link.dataset.briefService;
        serviceInput.dispatchEvent(new Event("change", { bubbles: true }));
      }
    });
  });

  const form = document.getElementById("contactForm");
  const review = document.getElementById("formResponse");
  const preview = document.getElementById("briefPreview");
  const draftLink = document.getElementById("emailDraft");
  const status = document.getElementById("copyStatus");
  const services = { web: "Web Application / ระบบองค์กร", mobile: "Mobile Application", cloud: "Cloud & Infrastructure", web3: "Web3 / NFT", other: "ต้องการคำแนะนำ" };
  form?.addEventListener("submit", event => {
    event.preventDefault();
    ["userName", "userMessage"].forEach(id => {
      const field = document.getElementById(id);
      field.value = field.value.trim();
    });
    if (!form.reportValidity()) return;
    const data = new FormData(form);
    const read = key => String(data.get(key) || "-").trim();
    const service = services[read("service")] || services.other;
    const message = [
      "เรียน ทีม MONSTOPIA", "", "สนใจปรึกษาโครงการ โดยมีรายละเอียดดังนี้", "",
      "ชื่อ: " + read("name"), "บริษัท: " + read("company"),
      "อีเมล: " + read("email"), "โทรศัพท์: " + read("phone"),
      "บริการ: " + service, "งบประมาณ: " + read("budget"),
      "ช่วงเวลาที่ต้องการเริ่ม: " + read("timeline"), "", "รายละเอียดโครงการ:", read("message")
    ].join("\n");
    preview.value = message;
    draftLink.href = "mailto:contact@monstopia.co.th?subject=" + encodeURIComponent("ปรึกษาโครงการ — " + service) + "&body=" + encodeURIComponent(message);
    review.hidden = false;
    status.textContent = "";
    review.scrollIntoView({ behavior: matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth", block: "nearest" });
    preview.focus({ preventScroll: true });
  });
  form?.addEventListener("input", () => { review.hidden = true; });
  form?.addEventListener("change", () => { review.hidden = true; });
  document.getElementById("copyBrief")?.addEventListener("click", async () => {
    try {
      await navigator.clipboard.writeText(preview.value);
      status.textContent = "คัดลอกแล้ว ส่งข้อความนี้ไปที่ contact@monstopia.co.th ได้เลย";
    } catch {
      preview.focus();
      preview.select();
      status.textContent = "เลือกข้อความให้แล้ว กรุณากด Ctrl+C หรือคัดลอกจากเมนูของอุปกรณ์";
    }
  });
});
