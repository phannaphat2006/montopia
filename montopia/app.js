document.addEventListener("DOMContentLoaded", () => {
  const toggle = document.getElementById("mobileToggle");
  const nav = document.querySelector(".nav-links");
  if (toggle && nav) {
    toggle.setAttribute("aria-expanded", "false");
    toggle.addEventListener("click", () => {
      const open = nav.classList.toggle("active");
      toggle.setAttribute("aria-expanded", String(open));
      toggle.innerHTML = `<i class="fa-solid fa-${open ? "xmark" : "bars"}"></i>`;
    });
    nav.querySelectorAll("a").forEach(link => link.addEventListener("click", () => {
      nav.classList.remove("active");
      toggle.setAttribute("aria-expanded", "false");
      toggle.innerHTML = '<i class="fa-solid fa-bars"></i>';
    }));
  }

  const canvases = ["heroCanvas", "navCanvas"].map(id => document.getElementById(id)).filter(Boolean);
  const image = new Image();
  image.src = "logo.jpg";
  image.onload = () => canvases.forEach(canvas => {
    canvas.width = image.width;
    canvas.height = image.height;
    const ctx = canvas.getContext("2d");
    ctx.drawImage(image, 0, 0);
    const pixels = ctx.getImageData(0, 0, canvas.width, canvas.height);
    for (let i = 0; i < pixels.data.length; i += 4) {
      const light = (pixels.data[i] + pixels.data[i + 1] + pixels.data[i + 2]) / 3;
      if (light > 210) pixels.data[i + 3] = 0;
      else [pixels.data[i], pixels.data[i + 1], pixels.data[i + 2]] = [255, 59, 48];
    }
    ctx.putImageData(pixels, 0, 0);
  });

  const counterObserver = new IntersectionObserver((entries, observer) => entries.forEach(entry => {
    if (!entry.isIntersecting) return;
    const counter = entry.target;
    const target = Number(counter.dataset.target || 0);
    const start = performance.now();
    const draw = now => {
      const progress = Math.min((now - start) / 900, 1);
      counter.textContent = Math.round(target * (1 - Math.pow(1 - progress, 3)));
      if (progress < 1) requestAnimationFrame(draw);
    };
    requestAnimationFrame(draw);
    observer.unobserve(counter);
  }), { threshold: .5 });
  document.querySelectorAll(".count-up").forEach(counter => counterObserver.observe(counter));

  const form = document.getElementById("contactForm");
  const response = document.getElementById("formResponse");
  if (form && response) form.addEventListener("submit", event => {
    event.preventDefault();
    if (!form.checkValidity()) return form.reportValidity();
    const data = new FormData(form);
    const services = { web: "Web Application", mobile: "Mobile Application", cloud: "Cloud & Infrastructure", web3: "Web3 / Blockchain", other: "โครงการอื่น ๆ" };
    const service = services[data.get("service")] || "โครงการใหม่";
    const subject = encodeURIComponent(`ขอปรึกษาโครงการ: ${service}`);
    const body = encodeURIComponent(`ชื่อ/บริษัท: ${data.get("name")}\nอีเมล: ${data.get("email")}\nโทรศัพท์: ${data.get("phone") || "-"}\nบริการ: ${service}\nงบประมาณ: ${data.get("budget")}\nต้องการเริ่ม: ${data.get("timeline")}\n\nรายละเอียดโครงการ:\n${data.get("message")}`);
    response.style.display = "block";
    response.textContent = "กำลังเปิดโปรแกรมอีเมลของคุณ…";
    window.location.href = `mailto:contact@monstopia.co.th?subject=${subject}&body=${body}`;
  });
});
