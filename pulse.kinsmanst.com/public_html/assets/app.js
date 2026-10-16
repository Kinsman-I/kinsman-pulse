/**
 * Interações progressivas da interface autenticada.
 * Cada bloco abaixo só atua quando o componente existe na página atual.
 */
document.addEventListener("DOMContentLoaded", () => {
  // Biblioteca Pulse: saída visível, clique no fundo e tecla Esc.
  const libraryDrawer = document.querySelector(".library-drawer");
  if (libraryDrawer) {
    const closeLibrary = () => {
      libraryDrawer.removeAttribute("open");
      const currentUrl = new URL(window.location.href);
      ["library", "lib_q", "lib_group"].forEach((key) =>
        currentUrl.searchParams.delete(key),
      );
      history.replaceState({}, "", currentUrl);
      libraryDrawer.querySelector("summary")?.focus();
    };
    libraryDrawer
      .querySelectorAll("[data-close-library]")
      .forEach((control) => control.addEventListener("click", closeLibrary));
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && libraryDrawer.open) closeLibrary();
    });
  }

  // Reposiciona o editor no cartão de exercício em edição.
  const editForm = [...document.querySelectorAll("form")].find(
    (form) =>
      form.querySelector('[name="action"][value="save_workout_item"]') &&
      Number(form.querySelector('[name="item_id"]')?.value) > 0,
  );
  if (editForm) {
    const itemId = editForm.querySelector('[name="item_id"]').value;
    const editLink = [
      ...document.querySelectorAll(".workout-list .mini-link"),
    ].find(
      (link) =>
        new URL(link.href, location.href).searchParams.get("edit_item") ===
        itemId,
    );
    const card = editLink?.closest("article");
    const editor = editForm.closest("section.panel");
    if (card && editor && !card.contains(editor)) {
      editor.classList.remove("sticky");
      editor.classList.add("workout-inline-editor");
      card.append(editor);
      card.id = "editing-exercise-" + itemId;
      card.scrollIntoView({ block: "start" });
    }
  }
  // Controla seleção e validação do diálogo de exportação de treinos.
  const exportDialog = document.querySelector(".workout-export-dialog");
  if (exportDialog) {
    const form = exportDialog.querySelector("form");
    const options = [...form.querySelectorAll('input[name="ids[]"]')];
    const all = form.querySelector("[data-export-all]");
    const submit = form.querySelector("[data-export-submit]");
    const update = () => {
      const count = options.filter((option) => option.checked).length;
      all.checked = count === options.length;
      all.indeterminate = count > 0 && count < options.length;
      submit.disabled = count === 0;
      form.querySelector(".export-count").textContent = count
        ? count + " treino" + (count === 1 ? " selecionado" : "s selecionados")
        : "Nenhum treino selecionado";
    };
    document
      .querySelector("[data-open-workout-export]")
      ?.addEventListener("click", () => exportDialog.showModal());
    form
      .querySelector("[data-close-workout-export]")
      .addEventListener("click", () => exportDialog.close());
    all.addEventListener("change", () => {
      options.forEach((option) => (option.checked = all.checked));
      update();
    });
    options.forEach((option) => option.addEventListener("change", update));
    form.addEventListener("submit", (event) => {
      if (!options.some((option) => option.checked)) event.preventDefault();
    });
    update();
  }
  // Mantém o menu móvel acessível, inclusive ao clicar fora dele.
  const button = document.querySelector(".menu-toggle");
  const sidebar = document.querySelector(".sidebar");

  button?.addEventListener("click", (event) => {
    event.stopPropagation();
    sidebar?.classList.toggle("open");
    button.setAttribute(
      "aria-expanded",
      String(sidebar?.classList.contains("open")),
    );
  });

  document.addEventListener("click", (event) => {
    const clickedOutsideMenu =
      !sidebar?.contains(event.target) && !button?.contains(event.target);
    if (
      innerWidth <= 900 &&
      sidebar?.classList.contains("open") &&
      clickedOutsideMenu
    ) {
      sidebar.classList.remove("open");
      button?.setAttribute("aria-expanded", "false");
    }
  });
  // Exibe dicas de grupos musculares no mapa interativo.
  const muscleZones = document.querySelectorAll(".muscle-zone");
  if (muscleZones.length) {
    const tip = document.createElement("div");
    tip.className = "muscle-tip";
    tip.hidden = true;
    document.body.append(tip);
    muscleZones.forEach((zone) => {
      const label = zone.querySelector("title");
      if (!label) return;
      zone.addEventListener("mouseenter", () => {
        tip.textContent = label.textContent;
        tip.hidden = false;
        const rect = zone.getBoundingClientRect();
        tip.style.left = `${rect.left + rect.width / 2}px`;
        tip.style.top = `${Math.max(6, rect.top - 36)}px`;
      });
      zone.addEventListener("mouseleave", () => {
        tip.hidden = true;
      });
      zone.addEventListener("click", () => {
        tip.hidden = true;
      });
    });
  }
  // Armazena a escolha do banner de cookies no navegador.
  const cookieBanner = document.getElementById("cookieBanner");
  const cookieChoice = localStorage.getItem("kinsman_cookie_consent");
  if (cookieBanner && !cookieChoice) cookieBanner.hidden = false;
  document.querySelectorAll("[data-cookie-choice]").forEach((choice) =>
    choice.addEventListener("click", () => {
      localStorage.setItem(
        "kinsman_cookie_consent",
        choice.dataset.cookieChoice,
      );
      localStorage.setItem(
        "kinsman_cookie_consent_at",
        new Date().toISOString(),
      );
      if (cookieBanner) cookieBanner.hidden = true;
    }),
  );
  document
    .getElementById("resetCookieChoice")
    ?.addEventListener("click", () => {
      localStorage.removeItem("kinsman_cookie_consent");
      localStorage.removeItem("kinsman_cookie_consent_at");
      if (cookieBanner) cookieBanner.hidden = false;
    });
  const colorInput = document.querySelector('input[name="primary_color"]');
  colorInput?.addEventListener("input", () => {
    document.documentElement.style.setProperty("--brand", colorInput.value);
    document.documentElement.style.setProperty(
      "--brand-dark",
      `color-mix(in srgb,${colorInput.value} 58%,#061f19)`,
    );
    document.documentElement.style.setProperty(
      "--brand-soft",
      `color-mix(in srgb,${colorInput.value} 11%,#fff)`,
    );
  });
  // Carrossel demonstrativo que respeita a preferência de reduzir movimento.
  const carousel = document.querySelector("[data-demo-carousel]");
  if (carousel) {
    const slides = [...carousel.querySelectorAll("[data-demo-slide]")];
    const dots = [...carousel.querySelectorAll("[data-demo-dot]")];
    const titles = [
      "Painel do profissional",
      "Gestão dos alunos",
      "Página do aluno",
      "Dieta, treino e evolução",
    ];
    const counter = carousel.querySelector("[data-demo-counter]");
    const title = carousel.querySelector("[data-demo-title]");
    let current = 0;
    let timer;
    const show = (index) => {
      current = (index + slides.length) % slides.length;
      slides.forEach((slide, i) => {
        const active = i === current;
        slide.classList.toggle("is-active", active);
        slide.setAttribute("aria-hidden", String(!active));
      });
      dots.forEach((dot, i) => {
        const active = i === current;
        dot.classList.toggle("is-active", active);
        dot.setAttribute("aria-selected", String(active));
      });
      if (counter) counter.textContent = `${current + 1} de ${slides.length}`;
      if (title) title.textContent = titles[current];
    };
    const autoplay = () => {
      clearInterval(timer);
      if (!matchMedia("(prefers-reduced-motion: reduce)").matches)
        timer = setInterval(() => show(current + 1), 7000);
    };
    carousel
      .querySelector("[data-demo-prev]")
      ?.addEventListener("click", () => {
        show(current - 1);
        autoplay();
      });
    carousel
      .querySelector("[data-demo-next]")
      ?.addEventListener("click", () => {
        show(current + 1);
        autoplay();
      });
    dots.forEach((dot, i) =>
      dot.addEventListener("click", () => {
        show(i);
        autoplay();
      }),
    );
    carousel.addEventListener("mouseenter", () => clearInterval(timer));
    carousel.addEventListener("mouseleave", autoplay);
    carousel.addEventListener("focusin", () => clearInterval(timer));
    carousel.addEventListener("focusout", autoplay);
    show(0);
    autoplay();
  }
  // Adiciona dados de execução ao checklist antes do envio assíncrono.
  const workoutCards = [...document.querySelectorAll(".exercise-check-card")];
  if (workoutCards.length) {
    workoutCards.forEach((card) => {
      const times = card.querySelector(".exercise-times");
      if (!times || card.querySelector(".performed-fields")) return;
      const fields = document.createElement("div");
      fields.className = "performed-fields";
      fields.innerHTML =
        '<label>Carga utilizada<input name="performed_load" maxlength="40" placeholder="Ex.: 12 kg"></label><label>Repetições feitas<input name="performed_repetitions" maxlength="40" placeholder="Ex.: 3 × 10"></label>';
      times.before(fields);
    });
    const originalFetch = window.fetch.bind(window);
    window.fetch = (input, options = {}) => {
      const body = options.body;
      if (
        body instanceof URLSearchParams &&
        body.get("action") === "update_workout_exercise"
      ) {
        const card = document.querySelector(
          `.exercise-check-card[data-item="${CSS.escape(body.get("workout_item_id") || "")}"]`,
        );
        if (card) {
          body.set(
            "performed_load",
            card.querySelector('[name="performed_load"]')?.value || "",
          );
          body.set(
            "performed_repetitions",
            card.querySelector('[name="performed_repetitions"]')?.value || "",
          );
        }
      }
      return originalFetch(input, options);
    };
  }
});

/*
 * Guia de primeiro acesso
 *
 * O estado fica no navegador do próprio usuário. Assim, cada perfil vê o
 * guia na primeira entrada, sem exigir mudança de banco ou interferir na
 * experiência de outras pessoas da mesma conta profissional.
 */
document.addEventListener("DOMContentLoaded", () => {
  if (!document.body.classList.contains("app-body")) return;

  const style = document.createElement("link");
  style.rel = "stylesheet";
  style.href = "assets/onboarding.css?v=2";
  style.dataset.pulseOnboarding = "true";
  document.head.append(style);

  const userName =
    document.querySelector(".sidebar-foot strong")?.textContent?.trim() || "user";
  const roleLabel =
    document.querySelector(".sidebar-foot span")?.textContent?.trim().toLowerCase() ||
    "usuário";
  const role = roleLabel.includes("administrador")
    ? "admin"
    : roleLabel.includes("profissional")
      ? "professional"
      : "student";
  const storageKey = `pulse:first-access:v1:${role}:${userName}`;

  const guides = {
    admin: {
      audience: "ADMINISTRAÇÃO DA PLATAFORMA",
      title: "A operação da plataforma, em ordem.",
      steps: [
        ["Cadastre os profissionais", "Crie os acessos, acompanhe os limites de alunos e mantenha as assinaturas organizadas.", "Profissionais", "index.php?page=users"],
        ["Acompanhe o financeiro", "Consulte os planos e a situação das assinaturas em um só lugar.", "Abrir financeiro", "index.php?page=finance"],
        ["Mantenha o catálogo pronto", "A biblioteca de exercícios fica preparada para os profissionais usarem nas fichas.", "Ver exercícios", "index.php?page=exercises"],
      ],
    },
    professional: {
      audience: "PRIMEIROS PASSOS DO PROFISSIONAL",
      title: "Vamos montar seu primeiro atendimento.",
      steps: [
        ["1. Cadastre o aluno", "Inclua os dados básicos, objetivo, altura e peso. O Pulse já cria o acesso dele.", "Cadastrar aluno", "index.php?page=students"],
        ["2. Envie e revise a anamnese", "No prontuário, solicite a anamnese e use as respostas para entender a rotina e os objetivos.", "Abrir alunos", "index.php?page=students"],
        ["3. Monte treino ou plano alimentar", "Pelo prontuário, prepare a ficha de treino ou a dieta e publique somente depois de revisar.", "Ver prontuários", "index.php?page=students"],
        ["4. Acompanhe e ajuste", "Registre avaliações, confira a evolução e responda as mensagens sem sair do painel.", "Voltar ao painel", "index.php?page=dashboard"],
      ],
    },
    student: {
      audience: "BEM-VINDO(A) AO PULSE",
      title: "Seu acompanhamento começa aqui.",
      steps: [
        ["1. Complete sua anamnese", "Conte ao profissional seus objetivos, rotina e restrições para que ele prepare suas orientações.", "Abrir anamnese", "index.php?page=my-anamnesis"],
        ["2. Consulte o que foi publicado", "Seu treino e plano alimentar aparecem aqui assim que o profissional fizer a publicação.", "Ver início", "index.php?page=dashboard"],
        ["3. Registre sua evolução", "Atualize peso e medidas para acompanhar seu progresso junto com o profissional.", "Ver evolução", "index.php?page=my-progress"],
      ],
    },
  };

  const guide = guides[role];
  let currentStep = 0;
  let previousFocus = null;
  let modal;

  const element = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text) node.textContent = text;
    return node;
  };

  const renderStep = () => {
    if (!modal) return;
    const [heading, copy, actionLabel, actionUrl] = guide.steps[currentStep];
    modal.querySelector("[data-guide-step]").textContent = `Passo ${currentStep + 1} de ${guide.steps.length}`;
    modal.querySelector("[data-guide-heading]").textContent = heading;
    modal.querySelector("[data-guide-copy]").textContent = copy;
    const action = modal.querySelector("[data-guide-action]");
    action.textContent = actionLabel;
    action.href = actionUrl;
    modal.querySelectorAll("[data-guide-dot]").forEach((dot, index) => {
      dot.classList.toggle("is-current", index <= currentStep);
    });
    const back = modal.querySelector("[data-guide-back]");
    const next = modal.querySelector("[data-guide-next]");
    back.hidden = currentStep === 0;
    next.textContent = currentStep === guide.steps.length - 1 ? "Começar agora" : "Próximo";
  };

  const closeGuide = (remember = true) => {
    if (!modal) return;
    if (remember) localStorage.setItem(storageKey, "completed");
    modal.hidden = true;
    previousFocus?.focus();
  };

  const openGuide = () => {
    if (!modal) return;
    previousFocus = document.activeElement;
    currentStep = 0;
    renderStep();
    modal.hidden = false;
    modal.querySelector("[data-guide-next]").focus();
  };

  const buildGuide = () => {
    modal = element("section", "pulse-guide");
    modal.hidden = true;
    modal.setAttribute("role", "dialog");
    modal.setAttribute("aria-modal", "true");
    modal.setAttribute("aria-labelledby", "pulse-guide-title");

    const dialog = element("div", "pulse-guide__dialog");
    const eyebrow = element("p", "pulse-guide__eyebrow", guide.audience);
    const title = element("h2", "", guide.title);
    title.id = "pulse-guide-title";
    const description = element("p", "pulse-guide__description", "Siga cada etapa no seu ritmo. Este guia fica disponível no botão Ajuda.");
    const dots = element("div", "pulse-guide__steps");
    dots.setAttribute("aria-hidden", "true");
    guide.steps.forEach(() => {
      const dot = element("i");
      dot.dataset.guideDot = "true";
      dots.append(dot);
    });
    const count = element("small", "pulse-guide__count");
    count.dataset.guideStep = "true";
    const stepTitle = element("h3");
    stepTitle.dataset.guideHeading = "true";
    const stepCopy = element("p", "pulse-guide__text");
    stepCopy.dataset.guideCopy = "true";
    const stepAction = element("a", "pulse-guide__action");
    stepAction.dataset.guideAction = "true";
    const actions = element("div", "pulse-guide__actions");
    const skip = element("button", "pulse-guide__secondary", "Pular por enquanto");
    skip.type = "button";
    const actionSet = element("div");
    const back = element("button", "pulse-guide__secondary", "Voltar");
    back.type = "button";
    back.dataset.guideBack = "true";
    const next = element("button", "pulse-guide__primary", "Próximo");
    next.type = "button";
    next.dataset.guideNext = "true";
    actionSet.append(back, next);
    actions.append(skip, actionSet);
    dialog.append(eyebrow, title, description, dots, count, stepTitle, stepCopy, stepAction, actions);
    modal.append(dialog);
    document.body.append(modal);

    skip.addEventListener("click", () => {
      localStorage.setItem(storageKey, "dismissed");
      closeGuide(false);
    });
    back.addEventListener("click", () => {
      currentStep = Math.max(0, currentStep - 1);
      renderStep();
    });
    next.addEventListener("click", () => {
      if (currentStep === guide.steps.length - 1) {
        closeGuide();
        return;
      }
      currentStep += 1;
      renderStep();
    });
    modal.addEventListener("click", (event) => {
      if (event.target === modal) closeGuide();
    });
  };

  buildGuide();

  const trigger = element("button", "tutorial-trigger", "Primeiros passos");
  trigger.type = "button";
  trigger.setAttribute("aria-label", "Abrir guia de primeiros passos");
  trigger.addEventListener("click", openGuide);
  document.querySelector(".top-actions")?.prepend(trigger);
  document.querySelectorAll("[data-open-guide]").forEach((button) => {
    button.addEventListener("click", openGuide);
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && modal && !modal.hidden) closeGuide();
  });

  if (!localStorage.getItem(storageKey)) window.setTimeout(openGuide, 450);
});

/* Calculadora local de IMC: não grava dados e funciona para aluno e profissional. */
document.addEventListener("DOMContentLoaded", () => {
  if (!document.body.classList.contains("app-body")) return;

  const role = document.querySelector(".sidebar-foot span")?.textContent?.trim().toLowerCase() || "";
  const dashboard = new URLSearchParams(window.location.search).get("page") || "dashboard";

  if (dashboard === "dashboard") document.body.classList.add("dashboard-view");

  if (dashboard === "dashboard" && role.includes("profissional") && !document.querySelector("[data-bmi-calculator]")) {
    const calculator = document.createElement("section");
    calculator.className = "panel bmi-calculator";
    calculator.dataset.bmiCalculator = "true";
    calculator.innerHTML = '<div><p class="eyebrow">FERRAMENTA RÁPIDA</p><h2>Calculadora de IMC</h2><p>Use durante a avaliação do aluno. O resultado é informativo e não substitui avaliação profissional.</p></div><div class="bmi-calculator__fields"><label>Altura (m)<input data-bmi-height type="number" min="0.50" max="2.80" step=".01" placeholder="Ex.: 1,75"></label><label>Peso (kg)<input data-bmi-weight type="number" min="1" max="500" step=".1" placeholder="Ex.: 75"></label><output data-bmi-result>Informe altura e peso</output></div>';
    document.querySelector(".operational-hub")?.after(calculator);
  }

  document.querySelectorAll("[data-bmi-calculator]").forEach((calculator) => {
    const height = calculator.querySelector("[data-bmi-height]");
    const weight = calculator.querySelector("[data-bmi-weight]");
    const result = calculator.querySelector("[data-bmi-result]");
    if (!height || !weight || !result) return;

    const classify = (value) => value < 18.5 ? "Abaixo do peso" : value < 25 ? "Faixa adequada" : value < 30 ? "Sobrepeso" : "Obesidade";
    const calculate = () => {
      const h = Number(String(height.value).replace(",", "."));
      const w = Number(String(weight.value).replace(",", "."));
      if (!h || !w || h < .5 || h > 2.8 || w < 1 || w > 500) {
        result.textContent = "Informe altura e peso";
        return;
      }
      const value = w / (h * h);
      result.textContent = `IMC ${value.toFixed(1).replace(".", ",")} · ${classify(value)}`;
    };
    height.addEventListener("input", calculate);
    weight.addEventListener("input", calculate);
    calculate();
  });
});
