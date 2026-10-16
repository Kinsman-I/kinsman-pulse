<?php declare(strict_types=1); $registerUrl=rtrim((string)env('APP_URL','https://pulse.kinsmanst.com'),'/').'/index.php?page=register'; ?>
<!-- Landing pública isolada: utilize as classes pl-* de assets/landing.css. -->
<link rel="stylesheet" href="assets/landing.css?v=47">
<div class="pulse-landing">
<section class="pl-hero">
 <div class="pl-copy"><p class="pl-eyebrow">GESTÃO PARA PERSONAL TRAINER</p><h1>Pare de montar ficha no Word.<br><em>Entregue treino profissional.</em></h1><p class="pl-lead">Monte fichas completas em minutos, publique na área do aluno e acompanhe a evolução do seu atendimento.</p><div class="pl-actions"><a class="pl-button" href="<?=url('register')?>">Escolher meu plano</a><a class="pl-link" href="<?=url('home')?>#recursos">Ver recursos <span aria-hidden="true">→</span></a></div><p class="pl-note">Conta liberada após a confirmação segura do pagamento. Acesso pelo navegador.</p><div class="pl-tags"><span>Personal</span><span>Atendimento com a sua marca</span></div></div>
 <aside class="pl-overview"><p class="pl-eyebrow">COMO O PULSE AJUDA NO DIA A DIA</p><h2>Do primeiro aluno à próxima evolução.</h2><ol><li><span>01</span><div><h3>Cadastre o aluno</h3><p>Reúna dados pessoais, anamnese e objetivos.</p></div></li><li><span>02</span><div><h3>Prepare as orientações</h3><p>Crie fichas de treino conforme o objetivo de cada aluno.</p></div></li><li><span>03</span><div><h3>Revise e publique</h3><p>Disponibilize o conteúdo na área do aluno.</p></div></li><li><span>04</span><div><h3>Acompanhe a evolução</h3><p>Consulte os registros de peso e medidas.</p></div></li></ol></aside>
</section>
<section id="recursos" class="pl-section"><p class="pl-eyebrow">FEITO PARA A SUA ROTINA</p><h2>Uma plataforma à altura do seu acompanhamento.</h2><div class="pt-feature"><div class="pt-intro"><span class="pt-tag">PERSONAL &amp; TREINO</span><h3>Seu método.<br>Mais perto<br>do aluno.</h3><p>Transforme sua prescrição em uma experiência organizada, com a identidade do seu atendimento.</p><a href="<?=url('register')?>">Escolher meu plano <span aria-hidden="true">&#8599;</span></a><small>Conta criada somente após o pagamento confirmado.</small></div><div class="pt-content"><span class="pt-kicker">DA PRESCRIÇÃO AO ACOMPANHAMENTO</span><h4>O cuidado está nos detalhes.<br>O Pulse organiza cada um.</h4><ul><li><b>01</b><div><strong>Treinos com o seu método</strong><p>Fichas A, B, C e programas ajustados ao objetivo de cada aluno.</p></div></li><li><b>02</b><div><strong>Prescrição completa</strong><p>Exercícios, séries, repetições, carga e descanso em uma ficha clara.</p></div></li><li><b>03</b><div><strong>Revisão antes de publicar</strong><p>Confira os ajustes e libere o treino quando estiver pronto.</p></div></li><li><b>04</b><div><strong>Evolução sempre por perto</strong><p>Consulte o histórico e acompanhe os registros de peso e medidas.</p></div></li></ul><div class="pt-footer"><span>Fichas de treino</span><span>Área do aluno</span><span>Sua identidade</span></div></div></div></section>
<section class="pl-section pl-dark"><div><p class="pl-eyebrow">A SUA MARCA FAZ PARTE DA EXPERIÊNCIA</p><h2>Seu aluno consulta<br>o que você publicou.</h2><p class="pl-lead">Personalize a identidade do atendimento e entregue uma área organizada para consultar treinos e evolução.</p><a class="pl-button" href="#planos">Ver planos</a></div><div class="pl-benefits"><article><span>01</span><div><h3>Celular ou computador</h3><p>Acesso pelo navegador, sem depender de instalação.</p></div></article><article><span>02</span><div><h3>Orientações sempre à mão</h3><p>O aluno consulta o que você publicou na área dele.</p></div></article><article><span>03</span><div><h3>Com a sua identidade</h3><p>Logo e cor do profissional fazem parte da plataforma.</p></div></article></div></section>
<section class="pl-section"><p class="pl-eyebrow">COMECE EM POUCOS MINUTOS</p><h2>Do pagamento ao primeiro acompanhamento.</h2><div class="pl-steps"><article><b>01</b><h3>Escolha e pague seu plano</h3><p>O cadastro fica pendente e a conta só é criada após a confirmação da Asaas.</p></article><article><b>02</b><h3>Organize seus alunos</h3><p>Cadastre as informações e reúna a anamnese e o histórico de cada um.</p></article><article><b>03</b><h3>Revise e publique</h3><p>Prepare as orientações e disponibilize o conteúdo para o aluno acessar.</p></article></div></section>
<section id="planos" class="pl-section"><p class="pl-eyebrow">ESCOLHA COMO QUER ATENDER</p><h2>Planos e limites de alunos</h2><p class="pl-intro">Escolha o plano ideal para sua rotina. Seu acesso será criado após a confirmação do pagamento.</p><div class="pl-price-switch" role="tablist" aria-label="Periodicidade do plano">
<button type="button" class="is-active" data-cycle="annual">Plano anual <b>-38%</b></button>
<button type="button" data-cycle="monthly">Plano mensal</button>
</div><div class="pl-pricing pl-pricing-annual">
  <article data-plan="basic"><div class="pl-plan-top"><span class="pl-plan-icon">01</span><p class="pl-eyebrow">Basic</p></div><h3>Essencial para organizar seus treinos</h3><p class="pl-plan-capacity">Até <b>20 alunos</b></p><p class="pl-price" data-annual="R$ 297" data-monthly="R$ 39,90"><del>R$ 478,80</del> R$ 297<small>/ano</small></p><p class="pl-equivalent" data-annual="Equivale a R$ 24,75/mês">Equivale a R$ 24,75/mês</p><ul class="pl-plan-features"><li>Treinos e fichas completas</li><li>Área exclusiva para o aluno</li><li>Acompanhamento da evolução</li><li class="is-unavailable">Sem personalização de marca</li></ul><a class="pl-button" href="<?=e($registerUrl)?>&amp;plan=basic&amp;cycle=annual">Criar conta no Basic</a></article>
  <article class="is-featured" data-plan="plus"><span class="pl-popular">MAIS ESCOLHIDO</span><span class="pl-plan-badge" data-annual-only>Landing Page opcional por R$ 399</span><div class="pl-plan-top"><span class="pl-plan-icon">02</span><p class="pl-eyebrow">Plus</p></div><h3>Mais presença para sua marca crescer</h3><p class="pl-plan-capacity">Até <b>40 alunos</b></p><p class="pl-price" data-annual="R$ 594" data-monthly="R$ 79,90"><del>R$ 958,80</del> R$ 594<small>/ano</small></p><p class="pl-equivalent" data-annual="Equivale a R$ 49,50/mês">Equivale a R$ 49,50/mês</p><ul class="pl-plan-features"><li>Treinos e fichas completas</li><li>Logo, cores e marca personalizadas</li><li>Até o dobro de alunos do Basic</li><li data-annual-only>Landing Page disponível no anual</li></ul><a class="pl-button" href="<?=e($registerUrl)?>&amp;plan=plus&amp;cycle=annual&amp;start=now">Escolher plano Plus</a></article>
  <article data-plan="premium"><span class="pl-plan-badge is-premium" data-annual-only>Landing Page opcional por apenas R$ 99</span><div class="pl-plan-top"><span class="pl-plan-icon">03</span><p class="pl-eyebrow">Premium</p></div><h3>Estrutura completa para escalar</h3><p class="pl-plan-capacity">Até <b>100 alunos</b></p><p class="pl-price" data-annual="R$ 966" data-monthly="R$ 129,90"><del>R$ 1.558,80</del> R$ 966<small>/ano</small></p><p class="pl-equivalent" data-annual="Equivale a R$ 80,50/mês">Equivale a R$ 80,50/mês</p><ul class="pl-plan-features"><li>Treinos e fichas completas</li><li>Logo, cores e marca personalizadas</li><li>Capacidade para até 100 alunos</li><li data-annual-only>Landing Page por valor exclusivo</li></ul><a class="pl-button" href="<?=e($registerUrl)?>&amp;plan=premium&amp;cycle=annual&amp;start=now">Escolher Premium</a></article>
</div><div class="pl-landing-offer" data-annual-only><div><small>EXCLUSIVO DO PLANO ANUAL</small><strong>Transforme visitas em novos alunos</strong></div><span>Adicione uma Landing Page profissional ao pedido: R$ 399 no Plus ou somente R$ 99 no Premium. Pagamento único.</span></div></section>
<section class="pl-section pl-faq"><div><p class="pl-eyebrow">ANTES DE COMEÇAR</p><h2>Ficou alguma dúvida?</h2><a class="pl-link" href="<?=url('home')?>#recursos">Consultar recursos <span aria-hidden="true">→</span></a></div><div><details><summary>O Pulse é feito para personal trainers?</summary><p>Sim. O Pulse foi criado para organizar alunos, fichas de treino e a evolução de cada pessoa.</p></details><details><summary>O aluno precisa instalar um aplicativo?</summary><p>Não. O acesso é pelo navegador do celular ou computador, com o login do aluno.</p></details><details><summary>Quando minha conta é criada?</summary><p>Somente depois que a Asaas confirmar o pagamento. Antes disso, os dados permanecem como uma solicitação pendente e não dão acesso ao sistema.</p></details><details><summary>Consigo alterar os treinos antes de publicar?</summary><p>Sim. Você pode revisar a ficha, substituir exercícios e ajustar séries, repetições e descanso antes da publicação.</p></details><details><summary>Posso colocar minha logo?</summary><p>Nos planos Plus e Premium, você pode personalizar a logo e a cor do atendimento.</p></details><details><summary>Posso escolher mensal ou anual?</summary><p>Sim. Você escolhe a periodicidade antes de seguir para o pagamento seguro.</p></details><details><summary>O que o aluno consegue acessar?</summary><p>O aluno consulta os treinos publicados pelo profissional e acompanha seus registros de evolução.</p></details></div></section>
<section class="pl-final"><p class="pl-eyebrow">CONHEÇA O KINSMAN PULSE</p><h2>Organize seus treinos<br>com mais profissionalismo.</h2><p>Escolha seu plano e conclua o pagamento seguro para criar sua conta.</p><div class="pl-actions"><a class="pl-button" href="<?=url('register')?>">Escolher meu plano</a><a class="pl-link" href="<?=url('home')?>#recursos">Consultar recursos</a></div></section>
<section class="pl-contact">
  <div class="pl-contact-content">
    <p class="pl-eyebrow">FALE COM A KINSMAN</p>

    <h2>Quer conversar sobre o Pulse?</h2>
    <p>
      Fale diretamente comigo para tirar dúvidas e conhecer melhor a plataforma.
    </p>

    <div class="pl-contact-actions">
      <a
        class="pl-contact-button"
        href="https://www.instagram.com/kinsmansafetech/"
        target="_blank"
        rel="noopener"
      >
        <svg width="20" height="20" viewBox="0 0 448 512" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M224.1 141c-63.6 0-114.9 51.3-114.9 114.9s51.3 114.9 114.9 114.9S339 319.5 339 255.9 287.7 141 224.1 141zm0 189.6c-41.1 0-74.7-33.5-74.7-74.7s33.5-74.7 74.7-74.7 74.7 33.5 74.7 74.7-33.6 74.7-74.7 74.7zm146.4-194.3c0 14.9-12 26.8-26.8 26.8-14.9 0-26.8-12-26.8-26.8s12-26.8 26.8-26.8 26.8 12 26.8 26.8zm76.1 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37-2.1-147.9-2.1-184.9 0-35.8 1.7-67.6 9.9-93.9 36.1s-34.4 58-36.2 93.9c-2.1 37-2.1 147.9 0 184.9 1.7 35.9 9.9 67.7 36.2 93.9s58 34.4 93.9 36.2c37 2.1 147.9 2.1 184.9 0 35.9-1.7 67.7-9.9 93.9-36.2 26.2-26.2 34.4-58 36.2-93.9 2.1-37 2.1-147.8 0-184.8zM398.8 388c-7.8 19.6-22.9 34.7-42.6 42.6-29.5 11.7-99.5 9-132.1 9s-102.7 2.6-132.1-9c-19.6-7.8-34.7-22.9-42.6-42.6-11.7-29.5-9-99.5-9-132.1s-2.6-102.7 9-132.1c7.8-19.6 22.9-34.7 42.6-42.6 29.5-11.7 99.5-9 132.1-9s102.7-2.6 132.1 9c19.6 7.8 34.7 22.9 42.6 42.6 11.7 29.5 9 99.5 9 132.1s2.7 102.7-9 132.1z"/></svg>
    Instagram da Kinsman
  </a>

      <a
         class="pl-contact-button pl-contact-button-primary"
    href="https://wa.me/5531975989750?text=Ol%C3%A1!%20Vim%20pelo%20site%20do%20Pulse%20e%20quero%20saber%20mais."
    target="_blank"
    rel="noopener"
  >
    <svg width="20" height="20" viewBox="0 0 448 512" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"/></svg>
    Falar pelo WhatsApp
  </a>
    </div>
  </div>
</section>
<a class="pl-wpp-float" href="https://wa.me/5531975989750?text=Ol%C3%A1!%20Vim%20pelo%20site%20do%20Pulse%20e%20quero%20saber%20mais." target="_blank" rel="noopener" aria-label="Falar no WhatsApp"><svg width="32" height="32" viewBox="0 0 448 512" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"/></svg></a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const buttons = document.querySelectorAll('.pl-price-switch button');
  const prices = document.querySelectorAll('.pl-pricing .pl-price');
  const equivalents = document.querySelectorAll('.pl-pricing .pl-equivalent');
  const annualOnly = document.querySelectorAll('[data-annual-only]');
  const planLinks = document.querySelectorAll('.pl-pricing article[data-plan] .pl-button');

  buttons.forEach(function (button) {
    button.addEventListener('click', function () {
      const cycle = button.dataset.cycle;

      buttons.forEach(function (item) {
        item.classList.toggle('is-active', item === button);
      });

      prices.forEach(function (price) {
        const value = price.dataset[cycle];
        const previous = cycle === 'annual' ? price.querySelector('del')?.outerHTML || '' : '';
        price.innerHTML = previous + value + '<small>/' + (cycle === 'annual' ? 'ano' : 'mês') + '</small>';
      });
      equivalents.forEach(function (item) {
        item.textContent = cycle === 'annual' ? item.dataset.annual : '';
        item.hidden = cycle !== 'annual';
      });
      annualOnly.forEach(function (item) {
        item.hidden = cycle !== 'annual';
      });
      planLinks.forEach(function (link) {
        const plan = link.closest('[data-plan]').dataset.plan;
        link.href = '<?=e($registerUrl)?>&plan=' + encodeURIComponent(plan) + '&cycle=' + encodeURIComponent(cycle) + (plan === 'basic' ? '' : '&start=now');
      });
    });
  });
});
</script>
