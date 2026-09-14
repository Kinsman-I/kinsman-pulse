<?php
declare(strict_types=1);

function nav_items(array $u): array
{
    $items = match ($u['role']) {
        'admin' => [['dashboard','Visao geral','home'],['users','Profissionais','users'],['exercises','Banco de exercicios','catalog'],['finance','Financeiro','finance']],
        'professional' => [['dashboard','Visao geral','home'],['students','Meus alunos','users'],['muscle-map','Mapa muscular','body'],['exercises','Catalogo de exercicios','catalog'],['finance','Planos e financeiro','finance'],['branding','Minha marca','settings']],
        default => [['dashboard','Inicio','home'],['my-anamnesis','Minha anamnese','document'],['my-plan','Meu plano','nutrition'],['my-workouts','Meus treinos','workout'],['my-progress','Evolucao','progress'],['messages','Mensagens','message']],
    };
    if (($u['role'] ?? '') !== 'student') return $items;
    $service = (string)($u['access_service_type'] ?? 'complete');
    return array_values(array_filter($items, static function(array $item) use ($service): bool {
        if ($item[0] === 'my-plan' && $service === 'personal') return false;
        if ($item[0] === 'my-workouts' && $service === 'nutrition') return false;
        return true;
    }));
}

function interface_icon(string $name): string
{
    $paths = [
        'home'=>'<path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v10h13V10"/>',
        'users'=>'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'catalog'=>'<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/>',
        'finance'=>'<path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
        'body'=>'<circle cx="12" cy="5" r="3"/><path d="M8 10c1.2-1 2.5-1.5 4-1.5S14.8 9 16 10l2 5-3 1-1 6h-4l-1-6-3-1 2-5Z"/>',
        'settings'=>'<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21h-4v-.1A1.7 1.7 0 0 0 8.6 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1H3v-4h1a1.7 1.7 0 0 0 .6-1 1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6V3h4v1a1.7 1.7 0 0 0 1 .6 1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9c.12.38.33.72.6 1h1v4h-1c-.27.28-.48.62-.6 1Z"/>',
        'document'=>'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h8"/>',
        'nutrition'=>'<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/>',
        'workout'=>'<path d="M6 7v10M18 7v10M3 9v6M21 9v6M6 12h12"/>',
        'progress'=>'<path d="M3 17l6-6 4 4 8-9"/><path d="M15 6h6v6"/>',
        'message'=>'<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"/>',
        'scale'=>'<path d="M5 20a8 8 0 1 1 14 0Z"/><path d="m12 10 2-2M8 20h8"/>',
        'calendar'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'status'=>'<circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/>',
    ];
    return '<svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.($paths[$name]??$paths['document']).'</svg>';
}

function render(string $title, callable $body, bool $guest = false): void
{
    $u = current_user();
    $tenantColor = $u['primary_color'] ?? '#16765f';
    ?><!doctype html><html lang="pt-BR"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <?php $canonicalPage = (string)($_GET['page'] ?? 'home'); $publicPage = in_array($canonicalPage, ['home','demo','privacy','terms','cookies'], true); $canonicalUrl = rtrim((string)env('APP_URL','https://pulse.kinsmanst.com'), '/') . ($canonicalPage === 'home' ? '/' : '/index.php?page=' . rawurlencode($canonicalPage)); ?>
    <?php if ($publicPage): ?><link rel="canonical" href="<?= e($canonicalUrl) ?>"><meta name="description" content="<?= e($canonicalPage === 'home' ? 'Kinsman Pulse: organize alunos, treinos, planos alimentares e evolução para personal trainers e nutricionistas.' : $title . ' do Kinsman Pulse.') ?>"><meta property="og:type" content="website"><meta property="og:url" content="<?= e($canonicalUrl) ?>"><meta property="og:title" content="<?= e($title) ?> · Kinsman Pulse"><meta property="og:description" content="Organize alunos, treinos, planos alimentares e evolução em um só lugar."><meta property="og:site_name" content="Kinsman Pulse"><meta name="twitter:card" content="summary">
    <?php endif; ?><link rel="icon" type="image/png" href="/assets/images/logo-kinsman.png?v=4"><link rel="apple-touch-icon" href="/assets/images/logo-kinsman.png?v=4">
    <meta name="theme-color" content="<?= e($tenantColor) ?>"><title><?= e($title) ?> · <?= e((string)env('APP_NAME')) ?></title>
    <link rel="stylesheet" href="assets/app.css?v=36">
    <link rel="stylesheet" href="assets/core.css?v=24">
    <?php if ($guest): ?><link rel="stylesheet" href="assets/auth.css?v=23"><?php endif; ?>
    <style>:root{--brand:<?= e($tenantColor) ?>;--brand-dark:color-mix(in srgb,var(--brand) 58%,#061f19);--brand-soft:color-mix(in srgb,var(--brand) 11%,#fff)}</style></head>
    <body class="<?= $guest ? 'guest-body' : 'app-body' ?>">
    <?php if ($guest): ?><header class="guest-top"><a class="brand" href="<?= url('home') ?>"><img class="official-logo" src="assets/images/logo-kinsman.png" alt="Logo Kinsman"><span><strong>Kinsman Pulse</strong><small>Acompanhamento inteligente</small></span></a><nav class="guest-nav"><a href="<?=url('home')?>#recursos">Recursos</a><a href="<?=url('terms')?>">Termos</a><a class="guest-login" href="<?= url('login') ?>">Entrar</a></nav></header><main class="guest-shell"><?php $body(); ?></main><footer class="guest-footer"><span>© <?=date('Y')?> Kinsman Tecnologia</span><nav><a href="<?=url('privacy')?>">Privacidade e LGPD</a><a href="<?=url('terms')?>">Termos de Uso</a><a href="<?=url('cookies')?>">Política de Cookies</a></nav></footer>
    <?php else: ?>
      <aside class="sidebar">
        <a class="brand" href="<?= url() ?>">
          <?php if (!empty($u['logo_path'])): ?><img src="<?= e($u['logo_path']) ?>" alt="Logo de <?= e($u['tenant_name']) ?>">
          <?php else: ?><img class="official-logo" src="assets/images/logo-kinsman.png" alt="Logo Kinsman"><?php endif; ?>
          <span><strong><?= e($u['tenant_name']) ?></strong><small>Pulse</small></span>
        </a>
        <nav><?php foreach(nav_items($u) as [$page,$label,$icon]): ?><a class="<?= (($_GET['page'] ?? 'dashboard') === $page) ? 'active' : '' ?>" href="<?= url($page) ?>"><i><?= interface_icon($icon) ?></i><span><?= e($label) ?></span></a><?php endforeach; ?></nav>
        <div class="sidebar-foot"><small>Conectado como</small><strong><?= e($u['name']) ?></strong><span><?= e(['admin'=>'Administrador','professional'=>'Profissional','student'=>'Aluno'][$u['role']] ?? ucfirst($u['role'])) ?></span></div>
      </aside>
      <div class="app-main"><header class="topbar"><button class="menu-toggle" type="button" aria-label="Abrir menu"><span></span><span></span><span></span></button><div><strong><?= e($title) ?></strong><small><?= e(date('d/m/Y')) ?></small></div><div class="top-actions"><span class="avatar"><?= e(strtoupper(substr($u['name'],0,2))) ?></span><a class="button ghost" href="<?= url('logout') ?>">Sair</a></div></header>
      <main class="content"><?php foreach(flashes() as $f): ?><div class="alert <?= e($f['type']) ?>"><?= e($f['message']) ?></div><?php endforeach; ?><?php $body(); ?></main>
      <footer class="footer"><span>© <?= date('Y') ?> <?= e($u['tenant_name']) ?> · <?= e($u['footer_text'] ?? 'Tecnologia Kinsman') ?></span><nav><a href="<?=url('privacy')?>">Privacidade e LGPD</a><a href="<?=url('terms')?>">Termos de Uso</a><a href="<?=url('cookies')?>">Cookies</a></nav></footer><?php if($u['role']==='student'):?><a class="help-float" href="<?=url('messages')?>"><span><?=interface_icon('message')?></span><b>Falar com o profissional</b><small>Abrir mensagens</small></a><?php endif;?></div>
    <?php endif; ?><aside class="cookie-banner" id="cookieBanner" role="dialog" aria-label="Preferências de cookies" hidden><div><strong>Privacidade e cookies</strong><p>Usamos cookies necessários para login e segurança. Cookies opcionais só serão utilizados com sua autorização.</p><a href="<?=url('cookies')?>">Saiba mais</a></div><div class="cookie-actions"><button type="button" data-cookie-choice="essential">Somente necessários</button><button type="button" class="primary" data-cookie-choice="accepted">Aceitar cookies</button></div></aside><script src="assets/app.js?v=36" defer></script></body></html><?php
}

function page_header(string $eyebrow, string $title, string $copy = '', string $actions = ''): void
{ ?><div class="page-head"><div><small><?= e($eyebrow) ?></small><h1><?= e($title) ?></h1><?php if($copy): ?><p><?= e($copy) ?></p><?php endif; ?></div><div class="page-actions"><?= $actions ?></div></div><?php }

function metric(string $label, string|int $value, string $note, string $tone='green'): void
{ ?><article class="metric <?= e($tone) ?>"><span><?= e($label) ?></span><strong><?= e((string)$value) ?></strong><small><?= e($note) ?></small></article><?php }

function empty_state(string $title, string $text): void
{ ?><div class="empty"><b><?= e($title) ?></b><p><?= e($text) ?></p></div><?php }
