<?php
declare(strict_types=1);

/**
 * Camada de ações HTTP (POST).
 *
 * Mantém os handlers por domínio: autenticação, assinatura, profissionais,
 * alunos, catálogo, fichas, dieta, mensagens e execução de treino. Cada
 * handler valida o papel do usuário e redireciona para a página adequada.
 */

function handle_post(): void
{
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        return;
    }
    security_request_guard();
    verify_csrf();
    $action = (string) ($_POST["action"] ?? "");
    try {
        $turnstileActions = [
            "login" => "login",
            "register_professional" => "register",
            "forgot" => "forgot_password",
        ];
        if (isset($turnstileActions[$action])) {
            verify_turnstile($turnstileActions[$action]);
        }
        $u = current_user();
        if (
            ($u["role"] ?? null) === "professional" &&
            !in_array(
                $action,
                [
                    "start_asaas_checkout",
                    "cancel_subscription",
                    "request_account_deletion",
                ],
                true,
            ) &&
            professional_access_blocked($u)
        ) {
            throw new RuntimeException(
                "Sua assinatura não está ativa. Acesse o Financeiro para contratar ou regularizar o plano.",
            );
        }
        $professionalOnly = [
            "save_brand",
            "create_student",
            "update_record",
            "add_assessment",
            "create_workout",
            "generate_workout_draft",
            "save_workout_item",
            "delete_workout_item",
            "publish_workout",
            "delete_workout",
            "reply_student_message",
            "create_food_plan",
            "save_meal",
            "delete_meal",
            "publish_food_plan",
        ];
        if (
            ($u["role"] ?? null) === "admin" &&
            in_array($action, $professionalOnly, true)
        ) {
            throw new RuntimeException(
                "Esta operação é exclusiva do profissional responsável pelo aluno.",
            );
        }
        match ($action) {
            "login" => action_login(),
            "register_professional" => action_register_professional(),
            "forgot" => action_forgot(),
            "reset" => action_reset(),
            "save_brand" => action_save_brand(),
            "create_professional" => action_create_professional(),
            "update_professional_plan" => action_update_professional_plan(),
            "start_asaas_checkout" => action_start_asaas_checkout(),
            "cancel_subscription" => action_cancel_subscription(),
            "request_account_deletion" => action_request_account_deletion(),
            "create_student" => action_create_student(),
            "update_record" => action_update_record(),
            "add_assessment" => action_add_assessment(),
            "save_exercise" => action_save_exercise(),
            "copy_global_exercise" => action_copy_global_exercise(),
            "copy_all_global_exercises" => action_copy_all_global_exercises(),
            "delete_exercise" => action_delete_exercise(),
            "create_workout" => action_create_workout(),
            "generate_workout_draft" => action_generate_workout_draft(),
            "save_workout_item" => action_save_workout_item(),
            "delete_workout_item" => action_delete_workout_item(),
            "publish_workout" => action_publish_workout(),
            "delete_workout" => action_delete_workout(),
            "create_food_plan" => action_create_food_plan(),
            "save_meal" => action_save_meal(),
            "delete_meal" => action_delete_meal(),
            "publish_food_plan" => action_publish_food_plan(),
            "update_weight" => action_update_weight(),
            "save_anamnesis" => action_save_anamnesis(),
            "send_professional_message" => action_send_professional_message(),
            "reply_student_message" => action_reply_student_message(),
            "start_workout" => action_start_workout(),
            "control_workout" => action_control_workout(),
            "toggle_workout_pause" => action_toggle_workout_pause(),
            "update_workout_exercise" => action_update_workout_exercise(),
            "finish_workout" => action_finish_workout(),
            default => throw new RuntimeException("Acao invalida."),
        };
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        flash(
            "error",
            env("APP_DEBUG", "false") === "true"
                ? $e->getMessage()
                : "Nao foi possivel concluir. Confira os dados e tente novamente.",
        );
        redirect(back_url());
    }
}

// -----------------------------------------------------------------------------
// Autenticação, cadastro e recuperação de senha.
// -----------------------------------------------------------------------------
/** Processa a autenticação e renova o identificador de sessão após o login. */
function action_login(): never
{
    $identifier = trim(
        (string) ($_POST["identifier"] ?? ($_POST["email"] ?? "")),
    );
    if (str_contains($identifier, "@")) {
        $stmt = db()->prepare(
            "SELECT * FROM users WHERE email=? AND active=1 LIMIT 1",
        );
        $stmt->execute([strtolower($identifier)]);
    } else {
        $phone = preg_replace("/\D/", "", $identifier);
        if ($phone === "") {
            throw new RuntimeException("Informe um e-mail ou telefone válido.");
        }
        $stmt = db()->prepare(
            "SELECT * FROM users WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'(',''),')',''),'+','')=? AND active=1",
        );
        $stmt->execute([$phone]);
    }
    $matches = $stmt->fetchAll();
    if (count($matches) > 1) {
        throw new RuntimeException(
            "Este telefone está em mais de uma conta. Entre com seu e-mail.",
        );
    }
    $u = $matches[0] ?? null;
    if (
        !$u ||
        !password_verify((string) $_POST["password"], $u["password_hash"])
    ) {
        flash("error", "E-mail, telefone ou senha incorretos.");
        redirect(url("login"));
    }
    session_regenerate_id(true);
    $_SESSION["user_id"] = $u["id"];
    $_SESSION["auth_fingerprint"] = hash(
        "sha256",
        (string) $u["password_hash"],
    );
    db()
        ->prepare("UPDATE users SET last_login_at=NOW() WHERE id=?")
        ->execute([$u["id"]]);
    audit("login");
    redirect(url());
}

function assert_strong_password(string $password): void
{
    $valid =
        strlen($password) >= 8 &&
        preg_match("/[a-z]/", $password) &&
        preg_match("/[A-Z]/", $password) &&
        preg_match("/\d/", $password) &&
        preg_match("/[^a-zA-Z\d]/", $password);

    if (!$valid) {
        throw new RuntimeException(
            "Use uma senha com pelo menos 8 caracteres, letra maiúscula, letra minúscula, número e símbolo.",
        );
    }
}

function action_register_professional(): never
{
    if (current_user()) {
        redirect(url());
    }
    $name = trim((string) ($_POST["name"] ?? ""));
    $email = strtolower(trim((string) ($_POST["email"] ?? "")));
    $phone = trim((string) ($_POST["phone"] ?? ""));
    $password = (string) ($_POST["password"] ?? "");
    $confirmation = (string) ($_POST["password_confirmation"] ?? "");
    $service = (string) ($_POST["service_type"] ?? "");
    $plan = (string) ($_POST["plan"] ?? "basic");
    $billingStart = (string) ($_POST["billing_start"] ?? "trial");
    if ($name === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException("Informe nome e e-mail válidos.");
    }
    assert_strong_password($password);

    if (!hash_equals($password, $confirmation)) {
        throw new RuntimeException("As senhas não coincidem.");
    }
    if (
        !in_array($service, ["personal", "nutrition", "complete"], true) ||
        !in_array($plan, ["basic", "plus", "premium"], true) ||
        !in_array($billingStart, ["trial", "now"], true)
    ) {
        throw new RuntimeException(
            "Modalidade, plano ou forma de início inválidos.",
        );
    }
    if (empty($_POST["accept_terms"])) {
        throw new RuntimeException(
            "Você precisa aceitar os Termos de Uso e a Política de Privacidade.",
        );
    }
    $exists = db()->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $exists->execute([$email]);
    if ($exists->fetchColumn()) {
        throw new RuntimeException("Este e-mail já possui cadastro.");
    }
    $tenantId = (int) env("PLATFORM_TENANT_ID", 0);
    if ($tenantId < 1) {
        $tenantId = (int) db()
            ->query(
                "SELECT tenant_id FROM users WHERE role='admin' AND active=1 ORDER BY id LIMIT 1",
            )
            ->fetchColumn();
    }
    if ($tenantId < 1) {
        throw new RuntimeException(
            "A plataforma ainda não está disponível para novos cadastros.",
        );
    }
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    db()->beginTransaction();
    db()
        ->prepare(
            "INSERT INTO users(tenant_id,role,name,email,password_hash,phone) VALUES(?,?,?,?,?,?)",
        )
        ->execute([
            $tenantId,
            "professional",
            $name,
            $email,
            $passwordHash,
            $phone ?: null,
        ]);
    $uid = (int) db()->lastInsertId();
    $referralCode = "KIN" . strtoupper(bin2hex(random_bytes(4)));
    $referredBy = null;
    $incomingRef = strtoupper(trim((string) ($_POST["referral_code"] ?? "")));
    if ($incomingRef !== "") {
        $ref = db()->prepare(
            "SELECT id FROM professionals WHERE referral_code=? LIMIT 1",
        );
        $ref->execute([$incomingRef]);
        $referredBy = (int) $ref->fetchColumn() ?: null;
    }
    $isTrial = $billingStart === "trial";
    $subscriptionStatus = $isTrial ? "trial" : "cancelled";
    $trialEndsAt = $isTrial
        ? new DateTimeImmutable("now")->modify("+7 days")->format("Y-m-d H:i:s")
        : null;
    db()
        ->prepare(
            "INSERT INTO professionals(tenant_id,user_id,service_type,subscription_plan,subscription_status,trial_ends_at,referral_code,referred_by_professional_id) VALUES(?,?,?,?,?,?,?,?)",
        )
        ->execute([
            $tenantId,
            $uid,
            $service,
            $plan,
            $subscriptionStatus,
            $trialEndsAt,
            $referralCode,
            $referredBy,
        ]);
    $pid = (int) db()->lastInsertId();
    db()
        ->prepare(
            "INSERT INTO professional_subscriptions(tenant_id,professional_id,plan_code,price_cents,status,starts_at,ends_at) VALUES(?,?,?,?,?,NOW(),?)",
        )
        ->execute([
            $tenantId,
            $pid,
            $plan,
            subscription_price($plan, $service),
            $subscriptionStatus,
            $trialEndsAt,
        ]);
    if ($referredBy) {
        db()
            ->prepare(
                "INSERT IGNORE INTO referral_rewards(referrer_professional_id,referred_professional_id,status) VALUES(?,?,'pending')",
            )
            ->execute([$referredBy, $pid]);
    }
    db()->commit();
    $welcomeSent = send_professional_welcome_email([
        "name" => $name,
        "email" => $email,
    ]);
    session_regenerate_id(true);
    $_SESSION["user_id"] = $uid;
    $_SESSION["auth_fingerprint"] = hash("sha256", $passwordHash);
    audit("professional.self_registered", "professional", $pid, [
        "plan" => $plan,
        "service_type" => $service,
        "billing_start" => $billingStart,
        "trial_ends_at" => $trialEndsAt,
        "welcome_email" => $welcomeSent,
    ]);
    if ($isTrial) {
        flash(
            "success",
            $welcomeSent
                ? "Seu teste de 7 dias começou. Enviamos o manual de uso para seu e-mail."
                : "Seu teste de 7 dias começou. Aproveite a plataforma.",
        );
        redirect(url("dashboard"));
    }
    try {
        redirect(
            create_asaas_checkout_for_professional(
                ["id" => $uid, "tenant_id" => $tenantId],
                $pid,
                $plan,
            ),
        );
    } catch (Throwable $e) {
        flash(
            "error",
            "Sua conta foi criada, mas não foi possível abrir o pagamento agora. Acesse o Financeiro para tentar novamente.",
        );
        redirect(url("finance"));
    }
}

function send_professional_welcome_email(array $professional): bool
{
    $to = preg_replace(
        '/[\r\n]+/',
        "",
        (string) ($professional["email"] ?? ""),
    );
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $name = htmlspecialchars(
        (string) ($professional["name"] ?? "Profissional"),
        ENT_QUOTES,
        "UTF-8",
    );
    $appName = (string) env("APP_NAME", "Kinsman Pulse");
    $appUrl = rtrim(
        (string) env("APP_URL", "https://pulse.kinsmanst.com"),
        "/",
    );
    $fromName = preg_replace(
        '/[\r\n]+/',
        " ",
        (string) env("MAIL_FROM_NAME", $appName),
    );
    $fromEmail = preg_replace(
        '/[\r\n]+/',
        "",
        (string) env("MAIL_FROM", "nao-responda@pulse.kinsmanst.com"),
    );
    $subject = "Boas-vindas ao " . $appName;
    if (function_exists("mb_encode_mimeheader")) {
        $subject = mb_encode_mimeheader($subject, "UTF-8");
    }
    $safeUrl = htmlspecialchars(
        $appUrl . "/index.php?page=login",
        ENT_QUOTES,
        "UTF-8",
    );
    $manualUrl = htmlspecialchars(
        $appUrl . "/assets/docs/manual-kinsman-pulse.pdf",
        ENT_QUOTES,
        "UTF-8",
    );
    $html =
        '<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;background:#eef5f2;font-family:Arial,sans-serif;color:#143f35">' .
        '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:30px 12px;background:#eef5f2"><tr><td align="center"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#fff;border-radius:20px;overflow:hidden">' .
        '<tr><td style="padding:28px 34px;background:#123f35;color:#fff"><strong style="font-size:21px">Kinsman Pulse</strong><br><span style="font-size:13px;color:#cfe1db">Gestão profissional de acompanhamento</span></td></tr>' .
        '<tr><td style="padding:36px 34px"><span style="font-size:11px;letter-spacing:1.8px;font-weight:bold;color:#17866d">BOAS-VINDAS</span><h1 style="margin:10px 0 14px;font-size:28px;line-height:1.25">Sua conta está pronta, ' .
        $name .
        '.</h1><p style="margin:0 0 24px;color:#607770;font-size:15px;line-height:1.65">Preparamos um caminho simples para você configurar sua marca, cadastrar o primeiro aluno e publicar suas prescrições com segurança.</p>' .
        '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td style="padding:14px;border:1px solid #d6e5df;border-radius:12px"><b>1. Ative seu plano</b><br><span style="color:#607770;font-size:13px">Escolha a modalidade na área Financeiro.</span></td></tr><tr><td height="9"></td></tr><tr><td style="padding:14px;border:1px solid #d6e5df;border-radius:12px"><b>2. Configure sua marca</b><br><span style="color:#607770;font-size:13px">Inclua nome, cor, especialidade e logo.</span></td></tr><tr><td height="9"></td></tr><tr><td style="padding:14px;border:1px solid #d6e5df;border-radius:12px"><b>3. Cadastre o primeiro aluno</b><br><span style="color:#607770;font-size:13px">Solicite a anamnese e prepare a primeira ficha.</span></td></tr></table>' .
        '<table role="presentation" cellspacing="0" cellpadding="0" style="margin-top:25px"><tr><td style="background:#17866d;border-radius:10px"><a href="' .
        $safeUrl .
        '" style="display:inline-block;padding:14px 22px;color:#fff;text-decoration:none;font-weight:bold">Acessar o Kinsman Pulse</a></td></tr></table>' .
        '<p style="margin:24px 0 0;color:#71857e;font-size:12px;line-height:1.6">O manual completo está anexado a este e-mail. Você também pode <a href="' .
        $manualUrl .
        '" style="color:#17866d">consultá-lo pela internet</a>.</p></td></tr>' .
        '<tr><td style="padding:18px 34px;background:#f5f9f7;text-align:center;color:#81928c;font-size:11px">Kinsman Tecnologia - Mensagem automática</td></tr></table></td></tr></table></body></html>';
    $manual = __DIR__ . "/../public_html/assets/docs/manual-kinsman-pulse.pdf";
    if (!is_file($manual)) {
        return @mail(
            $to,
            $subject,
            $html,
            implode("\r\n", [
                "MIME-Version: 1.0",
                "Content-Type: text/html; charset=UTF-8",
                "From: " . $fromName . " <" . $fromEmail . ">",
                "Reply-To: " . $fromEmail,
            ]),
        );
    }
    $boundary = "=_KinsmanPulse_" . bin2hex(random_bytes(12));
    $headers = [
        "MIME-Version: 1.0",
        "From: " . $fromName . " <" . $fromEmail . ">",
        "Reply-To: " . $fromEmail,
        'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
    ];
    $body =
        "--" .
        $boundary .
        "\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n" .
        $html .
        "\r\n";
    $body .=
        "--" .
        $boundary .
        "\r\nContent-Type: application/pdf; name=\"manual-kinsman-pulse.pdf\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"manual-kinsman-pulse.pdf\"\r\n\r\n" .
        chunk_split(base64_encode((string) file_get_contents($manual))) .
        "\r\n--" .
        $boundary .
        "--";
    return @mail($to, $subject, $body, implode("\r\n", $headers));
}

function action_forgot(): never
{
    $email = strtolower(trim((string) $_POST["email"]));
    $stmt = db()->prepare(
        "SELECT id,name,email FROM users WHERE email=? AND active=1",
    );
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    if ($u) {
        $token = bin2hex(random_bytes(32));
        db()->beginTransaction();
        db()
            ->prepare(
                "UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL",
            )
            ->execute([$u["id"]]);
        db()
            ->prepare(
                "INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 60 MINUTE))",
            )
            ->execute([$u["id"], hash("sha256", $token)]);
        db()
            ->prepare(
                "DELETE FROM password_resets WHERE expires_at < DATE_SUB(NOW(),INTERVAL 7 DAY)",
            )
            ->execute();
        db()->commit();
        $link =
            rtrim((string) env("APP_URL"), "/") .
            "/index.php?page=reset&token=" .
            $token;
        $appName = (string) env("APP_NAME", "Kinsman Pulse");
        $subject = "Redefinição de senha · " . $appName;
        if (function_exists("mb_encode_mimeheader")) {
            $subject = mb_encode_mimeheader($subject, "UTF-8");
        }
        $fromName = preg_replace(
            '/[\r\n]+/',
            " ",
            (string) env("MAIL_FROM_NAME", $appName),
        );
        $fromEmail = preg_replace(
            '/[\r\n]+/',
            "",
            (string) env("MAIL_FROM", "nao-responda@pulse.kinsmanst.com"),
        );
        $headers = [
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: 8bit",
            "From: " . $fromName . " <" . $fromEmail . ">",
            "Reply-To: " . $fromEmail,
        ];
        @mail(
            $u["email"],
            $subject,
            password_reset_email_html(
                $u,
                $link,
                password_reset_brand((int) $u["id"]),
            ),
            implode("\r\n", $headers),
        );
        if (env("APP_DEBUG", "false") === "true") {
            $_SESSION["debug_reset_link"] = $link;
        }
    }
    flash(
        "success",
        "Se o e-mail estiver cadastrado, enviaremos as instrucoes de recuperacao.",
    );
    redirect(url("forgot"));
}

function password_reset_brand(int $userId): array
{
    $stmt = db()->prepare("SELECT
      COALESCE(ownp.brand_name, studentp.brand_name, t.name, 'Kinsman Pulse') brand_name,
      COALESCE(ownp.logo_path, studentp.logo_path, t.logo_path) logo_path,
      COALESCE(ownp.primary_color, studentp.primary_color, t.primary_color, '#16765f') primary_color
      FROM users u
      JOIN tenants t ON t.id=u.tenant_id
      LEFT JOIN professionals ownp ON ownp.user_id=u.id
      LEFT JOIN students s ON s.user_id=u.id
      LEFT JOIN professionals studentp ON studentp.id=s.professional_id
      WHERE u.id=? LIMIT 1");
    $stmt->execute([$userId]);
    $brand = $stmt->fetch() ?: [];
    $color = (string) ($brand["primary_color"] ?? "#16765f");
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        $color = "#16765f";
    }
    return [
        "name" => (string) ($brand["brand_name"] ?? "Kinsman Pulse"),
        "logo" =>
            (string) ($brand["logo_path"] ?? "assets/images/logo-kinsman.png"),
        "color" => $color,
    ];
}

function password_reset_brand_from_token(string $token): array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return [
            "name" => "Kinsman Pulse",
            "logo" => "assets/images/logo-kinsman.png",
            "color" => "#16765f",
        ];
    }
    $stmt = db()->prepare(
        "SELECT user_id FROM password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() LIMIT 1",
    );
    $stmt->execute([hash("sha256", $token)]);
    $userId = (int) $stmt->fetchColumn();
    return $userId
        ? password_reset_brand($userId)
        : [
            "name" => "Kinsman Pulse",
            "logo" => "assets/images/logo-kinsman.png",
            "color" => "#16765f",
        ];
}

function password_reset_email_html(
    array $user,
    string $link,
    array $brand = [],
): string {
    $name = htmlspecialchars(
        (string) ($user["name"] ?? "Profissional"),
        ENT_QUOTES,
        "UTF-8",
    );
    $safeLink = htmlspecialchars($link, ENT_QUOTES, "UTF-8");
    $appUrl = rtrim(
        (string) env("APP_URL", "https://pulse.kinsmanst.com"),
        "/",
    );
    $brandName = htmlspecialchars(
        (string) ($brand["name"] ?? "Kinsman Pulse"),
        ENT_QUOTES,
        "UTF-8",
    );
    $brandColor = (string) ($brand["color"] ?? "#16765f");
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $brandColor)) {
        $brandColor = "#16765f";
    }
    $logoPath = (string) ($brand["logo"] ?? "assets/images/logo-kinsman.png");
    $logo = htmlspecialchars(
        preg_match("#^https?://#i", $logoPath)
            ? $logoPath
            : $appUrl . "/" . ltrim($logoPath, "/"),
        ENT_QUOTES,
        "UTF-8",
    );
    $year = date("Y");
    return '<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>' .
        '<body style="margin:0;padding:0;background:#f1f6f4;font-family:Arial,sans-serif;color:#173a34">' .
        '<div style="display:none;max-height:0;overflow:hidden">Recebemos uma solicitação para redefinir sua senha.</div>' .
        '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f6f4;padding:32px 12px"><tr><td align="center">' .
        '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border-radius:22px;overflow:hidden;box-shadow:0 14px 40px rgba(11,73,60,.12)">' .
        '<tr><td style="background:' .
        $brandColor .
        ';padding:25px 34px"><table role="presentation" cellspacing="0" cellpadding="0"><tr><td><img src="' .
        $logo .
        '" width="52" height="52" alt="' .
        $brandName .
        '" style="display:block;width:52px;height:52px;object-fit:contain;border-radius:11px;padding:4px;background:#fff"></td><td style="padding-left:13px;color:#fff"><strong style="font-size:18px">' .
        $brandName .
        '</strong><br><span style="font-size:13px;color:#ffffff">Acompanhamento pelo Kinsman Pulse</span></td></tr></table></td></tr>' .
        '<tr><td style="padding:38px 34px"><span style="font-size:11px;letter-spacing:2px;font-weight:bold;color:#16765f">ACESSO SEGURO</span><h1 style="margin:12px 0 14px;font-size:27px;line-height:1.25;color:#173a34">Redefina sua senha</h1><p style="margin:0 0 17px;font-size:16px;line-height:1.6;color:#607770">Olá, ' .
        $name .
        '.</p><p style="margin:0 0 25px;font-size:16px;line-height:1.6;color:#607770">Recebemos uma solicitação para alterar a senha da sua conta. Clique no botão abaixo para criar uma nova senha.</p>' .
        '<table role="presentation" cellspacing="0" cellpadding="0"><tr><td style="border-radius:12px;background:' .
        $brandColor .
        '"><a href="' .
        $safeLink .
        '" style="display:inline-block;padding:15px 25px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:bold">Criar nova senha&nbsp;</a></td></tr></table>' .
        '<div style="margin-top:27px;padding:16px;border-radius:12px;background:#f1f7f4;color:#5f766f;font-size:13px;line-height:1.55"><strong style="color:#173a34">Este link é válido por 60 minutos.</strong><br>Se você não solicitou a alteração, ignore este e-mail. Sua senha continuará a mesma.</div>' .
        '<p style="margin:24px 0 0;font-size:12px;line-height:1.5;color:#82948e">Se o botão não funcionar, copie e cole este endereço no navegador:<br><a href="' .
        $safeLink .
        '" style="color:#16765f;word-break:break-all">' .
        $safeLink .
        "</a></p></td></tr>" .
        '<tr><td style="padding:20px 34px;background:#f7faf9;text-align:center;color:#85968f;font-size:12px">© ' .
        $year .
        " Kinsman Tecnologia · Mensagem automática</td></tr></table></td></tr></table></body></html>";
}

function action_reset(): never
{
    $password = (string) ($_POST["password"] ?? "");
    $confirmation = (string) ($_POST["password_confirmation"] ?? "");

    assert_strong_password($password);

    if (!hash_equals($password, $confirmation)) {
        throw new RuntimeException("As senhas não coincidem.");
    }

    $hash = hash("sha256", (string) ($_POST["token"] ?? ""));

    $stmt = db()->prepare(
        'SELECT * FROM password_resets
         WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW()',
    );
    $stmt->execute([$hash]);
    $reset = $stmt->fetch();

    if (!$reset) {
        throw new RuntimeException("Link inválido ou expirado.");
    }

    db()->beginTransaction();

    db()
        ->prepare("UPDATE users SET password_hash=? WHERE id=?")
        ->execute([
            password_hash($password, PASSWORD_DEFAULT),
            $reset["user_id"],
        ]);

    db()
        ->prepare("UPDATE password_resets SET used_at=NOW() WHERE id=?")
        ->execute([$reset["id"]]);

    db()->commit();

    flash("success", "Senha alterada. Entre com a nova senha.");
    redirect(url("login"));
}

// -----------------------------------------------------------------------------
// Utilitários de profissional, assinatura e faturamento.
// -----------------------------------------------------------------------------
function professional_id(array $u): int
{
    $stmt = db()->prepare(
        "SELECT id FROM professionals WHERE user_id=? AND tenant_id=?",
    );
    $stmt->execute([$u["id"], $u["tenant_id"]]);
    return (int) $stmt->fetchColumn();
}

function professional_access_blocked(array $u): bool
{
    if (($u["role"] ?? null) !== "professional") {
        return false;
    }
    $stmt = db()->prepare(
        "SELECT id,subscription_status,trial_ends_at,access_until FROM professionals WHERE user_id=? AND tenant_id=?",
    );
    $stmt->execute([$u["id"], $u["tenant_id"]]);
    $row = $stmt->fetch();
    if (!$row) {
        return true;
    }
    if ($row["subscription_status"] === "active") {
        return false;
    }
    if (
        $row["subscription_status"] === "trial" &&
        !empty($row["trial_ends_at"]) &&
        strtotime($row["trial_ends_at"]) >= time()
    ) {
        return false;
    }
    if ($row["subscription_status"] === "trial") {
        db()->beginTransaction();
        db()
            ->prepare(
                "UPDATE professionals SET subscription_status='cancelled' WHERE id=? AND subscription_status='trial'",
            )
            ->execute([$row["id"]]);
        db()
            ->prepare(
                "UPDATE professional_subscriptions SET status='cancelled',ends_at=NOW() WHERE professional_id=? AND status='trial'",
            )
            ->execute([$row["id"]]);
        db()->commit();
    }
    if (
        in_array(
            $row["subscription_status"],
            ["past_due", "cancelled"],
            true,
        ) &&
        !empty($row["access_until"]) &&
        strtotime($row["access_until"]) >= time()
    ) {
        return false;
    }
    if ($row["subscription_status"] === "past_due") {
        db()
            ->prepare(
                "UPDATE professionals SET subscription_status='cancelled' WHERE user_id=? AND tenant_id=? AND subscription_status='past_due'",
            )
            ->execute([$u["id"], $u["tenant_id"]]);
    }
    return true;
}

function height_to_cm(mixed $value): ?float
{
    $raw = trim(str_replace(",", ".", (string) $value));
    if ($raw === "") {
        return null;
    }
    $height = (float) $raw;
    if ($height < 0.5 || $height > 2.8) {
        throw new RuntimeException(
            "Informe a altura em metros. Exemplo: 1,75.",
        );
    }
    return round($height * 100, 2);
}

// -----------------------------------------------------------------------------
// Configuração de marca, profissionais, alunos e catálogo.
// -----------------------------------------------------------------------------
/** Atualiza os dados de white-label da plataforma ou do profissional logado. */
function action_save_brand(): never
{
    $u = require_role(["admin", "professional"]);
    $logo = upload_image("logo", "logos");
    $color = (string) ($_POST["primary_color"] ?? "#16765f");
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        throw new RuntimeException("Cor invalida.");
    }
    if ($u["role"] === "admin") {
        $sql =
            "UPDATE tenants SET name=?,primary_color=?" .
            ($logo ? ",logo_path=?" : "") .
            " WHERE id=?";
        $args = [$_POST["name"], $color];
        if ($logo) {
            $args[] = $logo;
        }
        $args[] = $u["tenant_id"];
    } else {
        $sql =
            "UPDATE professionals SET brand_name=?,primary_color=?,whatsapp=?,instagram=?,welcome_message=?,footer_text=?" .
            ($logo ? ",logo_path=?" : "") .
            " WHERE user_id=? AND tenant_id=?";
        $args = [
            $_POST["name"],
            $color,
            $_POST["whatsapp"] ?: null,
            $_POST["instagram"] ?: null,
            $_POST["welcome_message"] ?: null,
            $_POST["footer_text"] ?: null,
        ];
        if ($logo) {
            $args[] = $logo;
        }
        $args[] = $u["id"];
        $args[] = $u["tenant_id"];
    }
    db()->prepare($sql)->execute($args);
    audit("brand.updated", "tenant", (int) $u["tenant_id"]);
    flash("success", "Identidade visual atualizada.");
    redirect(url("branding"));
}

function action_create_professional(): never
{
    $u = require_role("admin");
    $email = strtolower(trim((string) $_POST["email"]));
    $password = (string) $_POST["password"];
    if (strlen($password) < 8) {
        throw new RuntimeException("Senha muito curta.");
    }
    db()->beginTransaction();
    db()
        ->prepare(
            "INSERT INTO users(tenant_id,role,name,email,password_hash,phone) VALUES(?,?,?,?,?,?)",
        )
        ->execute([
            $u["tenant_id"],
            "professional",
            $_POST["name"],
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $_POST["phone"] ?: null,
        ]);
    $uid = (int) db()->lastInsertId();
    $referralCode = "KIN" . strtoupper(bin2hex(random_bytes(4)));
    db()
        ->prepare(
            "INSERT INTO professionals(tenant_id,user_id,service_type,registration_number,specialty,subscription_plan,subscription_status,trial_ends_at,referral_code) VALUES(?,?,?,?,?,'basic','cancelled',NULL,?)",
        )
        ->execute([
            $u["tenant_id"],
            $uid,
            $_POST["service_type"],
            $_POST["registration_number"] ?: null,
            $_POST["specialty"] ?: null,
            $referralCode,
        ]);
    $pid = (int) db()->lastInsertId();
    db()
        ->prepare(
            "INSERT INTO professional_subscriptions(tenant_id,professional_id,plan_code,price_cents,status,ends_at) VALUES(?,?,'basic',?,'cancelled',NOW())",
        )
        ->execute([
            $u["tenant_id"],
            $pid,
            subscription_price("basic", (string) $_POST["service_type"]),
        ]);
    db()->commit();
    audit("professional.created", "user", $uid);
    flash(
        "success",
        "Profissional cadastrado. A conta será liberada após a ativação do plano.",
    );
    redirect(url("users"));
}

function subscription_limit(string $plan): ?int
{
    return match ($plan) {
        "basic" => 20,
        "plus" => 40,
        "premium" => 100,
        default => 20,
    };
}

function subscription_price(string $plan, string $serviceType = "complete"): int
{
    if ($serviceType !== "complete") {
        return match ($plan) {
            "basic" => 5990,
            "plus" => 9990,
            "premium" => 16990,
            default => 5990,
        };
    }
    return match ($plan) {
        "basic" => 8990,
        "plus" => 12900,
        "premium" => 21900,
        default => 8990,
    };
}

function create_asaas_checkout_for_professional(
    array $user,
    int $professionalId,
    string $plan,
): string {
    if (!in_array($plan, ["basic", "plus", "premium"], true)) {
        throw new RuntimeException("Plano inválido.");
    }
    $q = db()->prepare("SELECT service_type FROM professionals WHERE id=?");
    $q->execute([$professionalId]);
    $service = (string) $q->fetchColumn();
    if ($service === "") {
        throw new RuntimeException("Profissional inválido.");
    }
    $price = subscription_price($plan, $service);
    $reference =
        "pulse-prof-" .
        $professionalId .
        "-" .
        $plan .
        "-" .
        bin2hex(random_bytes(6));
    $appUrl = rtrim((string) env("APP_URL"), "/");
    $payload = [
        "billingTypes" => ["CREDIT_CARD"],
        "chargeTypes" => ["RECURRENT"],
        "minutesToExpire" => 60,
        "externalReference" => $reference,
        "callback" => [
            "successUrl" =>
                $appUrl . "/index.php?page=finance&checkout=success",
            "cancelUrl" => $appUrl . "/index.php?page=finance&checkout=cancel",
            "expiredUrl" =>
                $appUrl . "/index.php?page=finance&checkout=expired",
        ],
        "items" => [
            [
                "name" => "Kinsman Pulse " . ucfirst($plan),
                "description" =>
                    "Assinatura mensal · " .
                    ($service === "complete"
                        ? "Personal + Nutrição"
                        : ($service === "nutrition"
                            ? "Nutrição"
                            : "Personal")),
                "quantity" => 1,
                "value" => $price / 100,
            ],
        ],
        "subscription" => [
            "cycle" => "MONTHLY",
            "nextDueDate" => date("Y-m-d H:i:s", time() + 300),
        ],
    ];
    $checkout = asaas_request("POST", "/checkouts", $payload);
    $checkoutId = (string) ($checkout["id"] ?? "");
    if ($checkoutId === "") {
        throw new RuntimeException("A Asaas não retornou o checkout.");
    }
    db()
        ->prepare(
            "UPDATE professional_subscriptions SET status='cancelled',ends_at=NOW() WHERE professional_id=? AND status='trial'",
        )
        ->execute([$professionalId]);
    db()
        ->prepare(
            'INSERT INTO professional_subscriptions(tenant_id,professional_id,plan_code,price_cents,status,asaas_checkout_id,external_reference) VALUES(?,?,?,?,\'cancelled\',?,?)',
        )
        ->execute([
            $user["tenant_id"],
            $professionalId,
            $plan,
            $price,
            $checkoutId,
            $reference,
        ]);
    return asaas_checkout_url($checkoutId);
}

function action_start_asaas_checkout(): never
{
    $u = require_role("professional");
    $plan = (string) ($_POST["plan"] ?? "");
    redirect(
        create_asaas_checkout_for_professional($u, professional_id($u), $plan),
    );
}

function action_cancel_subscription(): never
{
    $u = require_role("professional");
    $pid = professional_id($u);
    $q = db()->prepare(
        "SELECT asaas_subscription_id FROM professional_subscriptions WHERE professional_id=? AND asaas_subscription_id IS NOT NULL ORDER BY id DESC LIMIT 1",
    );
    $q->execute([$pid]);
    $subscriptionId = (string) $q->fetchColumn();
    $accessUntil = date("Y-m-d H:i:s");
    if ($subscriptionId !== "") {
        $remote = asaas_request(
            "GET",
            "/subscriptions/" . rawurlencode($subscriptionId),
        );
        if (!empty($remote["nextDueDate"])) {
            $accessUntil = date(
                "Y-m-d 23:59:59",
                strtotime((string) $remote["nextDueDate"]),
            );
        }
        asaas_request(
            "PUT",
            "/subscriptions/" . rawurlencode($subscriptionId),
            ["status" => "INACTIVE"],
        );
    }
    db()->beginTransaction();
    db()
        ->prepare(
            "UPDATE professionals SET subscription_status='cancelled',access_until=?,cancellation_requested_at=NOW() WHERE id=?",
        )
        ->execute([$accessUntil, $pid]);
    db()
        ->prepare(
            "UPDATE professional_subscriptions SET status='cancelled',ends_at=? WHERE professional_id=? AND status='active'",
        )
        ->execute([$accessUntil, $pid]);
    db()->commit();
    audit("subscription.cancelled", "professional", $pid, [
        "access_until" => $accessUntil,
    ]);
    flash(
        "success",
        "Renovação cancelada. Seu acesso ficará disponível até " .
            date("d/m/Y", strtotime($accessUntil)) .
            ".",
    );
    redirect(url("finance"));
}

function action_request_account_deletion(): never
{
    $u = require_auth();
    if ($u["role"] === "admin") {
        throw new RuntimeException(
            "A conta de administrador não pode ser excluída por esta tela. Entre em contato com o suporte Kinsman.",
        );
    }
    $password = (string) ($_POST["current_password"] ?? "");
    $confirmation = trim((string) ($_POST["delete_confirmation"] ?? ""));
    if (!password_verify($password, (string) $u["password_hash"])) {
        throw new RuntimeException("A senha informada não confere.");
    }
    if ($confirmation !== "EXCLUIR MINHA CONTA") {
        throw new RuntimeException(
            "Digite exatamente EXCLUIR MINHA CONTA para confirmar.",
        );
    }

    $professionalId = null;
    if ($u["role"] === "professional") {
        $q = db()->prepare(
            "SELECT id FROM professionals WHERE user_id=? AND tenant_id=?",
        );
        $q->execute([$u["id"], $u["tenant_id"]]);
        $professionalId = (int) $q->fetchColumn();
        if (!$professionalId) {
            throw new RuntimeException(
                "Não foi possível localizar o cadastro profissional.",
            );
        }
        $q = db()->prepare(
            "SELECT asaas_subscription_id FROM professional_subscriptions WHERE professional_id=? AND status IN ('active','past_due') AND asaas_subscription_id IS NOT NULL ORDER BY id DESC LIMIT 1",
        );
        $q->execute([$professionalId]);
        $asaasSubscriptionId = (string) $q->fetchColumn();
        if ($asaasSubscriptionId !== "") {
            asaas_request(
                "PUT",
                "/subscriptions/" . rawurlencode($asaasSubscriptionId),
                ["status" => "INACTIVE"],
            );
        }
    }

    db()->beginTransaction();
    try {
        db()
            ->prepare(
                'INSERT INTO account_deletion_requests(tenant_id,user_id,user_role,requested_at,scheduled_purge_at,status) VALUES(?,?,?,NOW(),DATE_ADD(NOW(), INTERVAL 30 DAY),\'pending\')',
            )
            ->execute([$u["tenant_id"], $u["id"], $u["role"]]);
        if ($professionalId !== null) {
            db()
                ->prepare(
                    "UPDATE professionals SET subscription_status='cancelled',access_until=NOW(),cancellation_requested_at=NOW() WHERE id=?",
                )
                ->execute([$professionalId]);
            db()
                ->prepare(
                    "UPDATE professional_subscriptions SET status='cancelled',ends_at=NOW() WHERE professional_id=? AND status IN ('active','past_due','trial')",
                )
                ->execute([$professionalId]);
            db()
                ->prepare(
                    "UPDATE users su JOIN students s ON s.user_id=su.id SET su.active=0 WHERE s.professional_id=?",
                )
                ->execute([$professionalId]);
        }
        db()
            ->prepare(
                "UPDATE password_resets SET used_at=COALESCE(used_at,NOW()) WHERE user_id=?",
            )
            ->execute([$u["id"]]);
        db()
            ->prepare("UPDATE users SET active=0 WHERE id=?")
            ->execute([$u["id"]]);
        db()
            ->prepare(
                "INSERT INTO audit_logs(tenant_id,user_id,action,entity_type,entity_id,details,ip_address) VALUES(?,?,?,?,?,?,?)",
            )
            ->execute([
                $u["tenant_id"],
                $u["id"],
                "account.deletion_requested",
                "user",
                $u["id"],
                json_encode([
                    "role" => $u["role"],
                    "professional_id" => $professionalId,
                    "scheduled_purge_at" => date(
                        "Y-m-d H:i:s",
                        time() + 30 * 86400,
                    ),
                ]),
                $_SERVER["REMOTE_ADDR"] ?? null,
            ]);
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $e;
    }
    logout();
    session_start();
    flash(
        "success",
        "Sua conta foi desativada e a solicitação de exclusão foi registrada.",
    );
    redirect(url("login"));
}

function action_update_professional_plan(): never
{
    $u = require_role("admin");
    $professionalId = (int) ($_POST["professional_id"] ?? 0);
    $plan = (string) ($_POST["subscription_plan"] ?? "basic");
    $status = (string) ($_POST["subscription_status"] ?? "active");
    if (
        !in_array($plan, ["basic", "plus", "premium"], true) ||
        !in_array($status, ["active", "past_due", "cancelled"], true)
    ) {
        throw new RuntimeException("Plano ou situação inválida.");
    }
    $check = db()->prepare(
        "SELECT id,service_type FROM professionals WHERE id=? AND tenant_id=?",
    );
    $check->execute([$professionalId, $u["tenant_id"]]);
    $professional = $check->fetch();
    if (!$professional) {
        throw new RuntimeException("Profissional inválido.");
    }
    if ($status === "cancelled") {
        $remote = db()->prepare(
            "SELECT asaas_subscription_id FROM professional_subscriptions WHERE professional_id=? AND asaas_subscription_id IS NOT NULL ORDER BY id DESC LIMIT 1",
        );
        $remote->execute([$professionalId]);
        $remoteId = (string) $remote->fetchColumn();
        if ($remoteId !== "") {
            asaas_request("PUT", "/subscriptions/" . rawurlencode($remoteId), [
                "status" => "INACTIVE",
            ]);
        }
    }
    db()->beginTransaction();
    db()
        ->prepare(
            "UPDATE professionals SET subscription_plan=?,subscription_status=? WHERE id=? AND tenant_id=?",
        )
        ->execute([$plan, $status, $professionalId, $u["tenant_id"]]);
    db()
        ->prepare(
            "UPDATE professional_subscriptions SET status='cancelled',ends_at=NOW() WHERE professional_id=? AND status IN ('trial','active','past_due')",
        )
        ->execute([$professionalId]);
    db()
        ->prepare(
            "INSERT INTO professional_subscriptions(tenant_id,professional_id,plan_code,price_cents,status) VALUES(?,?,?,?,?)",
        )
        ->execute([
            $u["tenant_id"],
            $professionalId,
            $plan,
            subscription_price($plan, $professional["service_type"]),
            $status,
        ]);
    db()->commit();
    audit("subscription.updated", "professional", $professionalId, [
        "plan" => $plan,
        "status" => $status,
    ]);
    flash("success", "Plano do profissional atualizado.");
    redirect(url("users"));
}

function action_create_student(): never
{
    $u = require_role(["admin", "professional"]);
    $prof =
        $u["role"] === "professional"
            ? professional_id($u)
            : (int) $_POST["professional_id"];
    $check = db()->prepare(
        "SELECT id,subscription_plan,subscription_status,trial_ends_at FROM professionals WHERE id=? AND tenant_id=?",
    );
    $check->execute([$prof, $u["tenant_id"]]);
    $professional = $check->fetch();
    if (!$professional) {
        throw new RuntimeException("Profissional invalido.");
    }
    $canCreateStudents =
        $professional["subscription_status"] === "active" ||
        ($professional["subscription_status"] === "trial" &&
            !empty($professional["trial_ends_at"]) &&
            strtotime($professional["trial_ends_at"]) >= time());
    if (!$canCreateStudents) {
        throw new RuntimeException(
            "Seu teste terminou ou sua assinatura não permite novos cadastros.",
        );
    }
    $limit = subscription_limit($professional["subscription_plan"]);
    $count = db()->prepare(
        "SELECT COUNT(*) FROM students s JOIN users u ON u.id=s.user_id WHERE s.professional_id=? AND u.active=1",
    );
    $count->execute([$prof]);
    $activeStudents = (int) $count->fetchColumn();
    if ($limit !== null && $activeStudents >= $limit) {
        throw new RuntimeException(
            "Limite do plano atingido. Faça upgrade para cadastrar outro aluno.",
        );
    }
    $password = (string) $_POST["password"];
    if (strlen($password) < 8) {
        throw new RuntimeException("Senha muito curta.");
    }
    db()->beginTransaction();
    db()
        ->prepare(
            "INSERT INTO users(tenant_id,role,name,email,password_hash,phone) VALUES(?,?,?,?,?,?)",
        )
        ->execute([
            $u["tenant_id"],
            "student",
            $_POST["name"],
            strtolower(trim((string) $_POST["email"])),
            password_hash($password, PASSWORD_DEFAULT),
            $_POST["phone"] ?: null,
        ]);
    $uid = (int) db()->lastInsertId();
    db()
        ->prepare(
            "INSERT INTO students(tenant_id,user_id,professional_id,service_type,objective,birth_date,height_cm,current_weight_kg,target_weight_kg,started_at) VALUES(?,?,?,?,?,?,?,?,?,CURDATE())",
        )
        ->execute([
            $u["tenant_id"],
            $uid,
            $prof,
            $_POST["service_type"],
            $_POST["objective"] ?: null,
            $_POST["birth_date"] ?: null,
            height_to_cm($_POST["height_m"] ?? null),
            $_POST["current_weight_kg"] ?: null,
            $_POST["target_weight_kg"] ?: null,
        ]);
    $sid = (int) db()->lastInsertId();
    if ($_POST["current_weight_kg"] !== "") {
        db()
            ->prepare(
                "INSERT INTO assessments(student_id,weight_kg,notes) VALUES(?,?,?)",
            )
            ->execute([$sid, $_POST["current_weight_kg"], "Cadastro inicial"]);
    }
    db()->commit();
    audit("student.created", "student", $sid);
    flash("success", "Aluno cadastrado e acesso criado.");
    redirect(url("student", ["id" => $sid]));
}

function get_scoped_student(int $id, array $u): array
{
    [$where, $args] = student_scope_sql($u, "s");
    $stmt = db()->prepare(
        "SELECT s.*,u.name,u.email,u.phone,p.user_id professional_user_id,pu.name professional_name FROM students s JOIN users u ON u.id=s.user_id JOIN professionals p ON p.id=s.professional_id JOIN users pu ON pu.id=p.user_id WHERE s.id=? AND $where",
    );
    $stmt->execute(array_merge([$id], $args));
    $s = $stmt->fetch();
    if (!$s) {
        http_response_code(404);
        exit("Aluno nao encontrado.");
    }
    return $s;
}

function action_update_record(): never
{
    $u = require_role(["admin", "professional"]);
    $id = (int) $_POST["student_id"];
    get_scoped_student($id, $u);
    db()
        ->prepare(
            "UPDATE students SET service_type=?,objective=?,birth_date=?,height_cm=?,target_weight_kg=?,clinical_notes=? WHERE id=?",
        )
        ->execute([
            $_POST["service_type"],
            $_POST["objective"] ?: null,
            $_POST["birth_date"] ?: null,
            height_to_cm($_POST["height_m"] ?? null),
            $_POST["target_weight_kg"] ?: null,
            $_POST["clinical_notes"] ?: null,
            $id,
        ]);
    audit("record.updated", "student", $id);
    flash("success", "Prontuario atualizado.");
    redirect(url("student", ["id" => $id]));
}

function action_add_assessment(): never
{
    $u = require_role(["admin", "professional"]);
    $id = (int) $_POST["student_id"];
    get_scoped_student($id, $u);
    db()->beginTransaction();
    db()
        ->prepare(
            "INSERT INTO assessments(student_id,weight_kg,waist_cm,hip_cm,body_fat_percent,notes) VALUES(?,?,?,?,?,?)",
        )
        ->execute([
            $id,
            $_POST["weight_kg"] ?: null,
            $_POST["waist_cm"] ?: null,
            $_POST["hip_cm"] ?: null,
            $_POST["body_fat_percent"] ?: null,
            $_POST["notes"] ?: null,
        ]);
    $aid = (int) db()->lastInsertId();
    if ($_POST["weight_kg"] !== "") {
        db()
            ->prepare("UPDATE students SET current_weight_kg=? WHERE id=?")
            ->execute([$_POST["weight_kg"], $id]);
    }
    $photo = upload_image("progress_photo", "progress");
    if ($photo) {
        db()
            ->prepare(
                "INSERT INTO progress_photos(student_id,assessment_id,image_path,photo_type) VALUES(?,?,?,?)",
            )
            ->execute([$id, $aid, $photo, $_POST["photo_type"] ?? "other"]);
    }
    db()->commit();
    flash("success", "Avaliacao adicionada ao prontuario.");
    redirect(url("student", ["id" => $id]));
}

function action_save_exercise(): never
{
    $u = require_role(["admin", "professional"]);
    $pid =
        $u["role"] === "professional"
            ? professional_id($u)
            : (int) $_POST["professional_id"];
    $valid = db()->prepare(
        "SELECT id FROM professionals WHERE id=? AND tenant_id=?",
    );
    $valid->execute([$pid, $u["tenant_id"]]);
    if (!$valid->fetchColumn()) {
        throw new RuntimeException("Profissional invalido.");
    }
    $id = (int) ($_POST["id"] ?? 0);
    $photo = upload_image("image", "exercises");
    if ($id) {
        $sql =
            "UPDATE exercise_catalog SET name=?,muscle_group=?,equipment=?,instructions=?,video_url=?" .
            ($photo ? ",image_path=?" : "") .
            " WHERE id=? AND professional_id=?";
        $args = [
            $_POST["name"],
            $_POST["muscle_group"] ?: null,
            $_POST["equipment"] ?: null,
            $_POST["instructions"] ?: null,
            $_POST["video_url"] ?: null,
        ];
        if ($photo) {
            $args[] = $photo;
        }
        $args[] = $id;
        $args[] = $pid;
        db()->prepare($sql)->execute($args);
    } else {
        db()
            ->prepare(
                "INSERT INTO exercise_catalog(tenant_id,professional_id,name,muscle_group,equipment,instructions,image_path,video_url) VALUES(?,?,?,?,?,?,?,?)",
            )
            ->execute([
                $u["tenant_id"],
                $pid,
                $_POST["name"],
                $_POST["muscle_group"] ?: null,
                $_POST["equipment"] ?: null,
                $_POST["instructions"] ?: null,
                $photo,
                $_POST["video_url"] ?: null,
            ]);
        $id = (int) db()->lastInsertId();
    }
    audit("exercise.saved", "exercise", $id);
    flash("success", "Exercicio salvo no catalogo.");
    redirect(url("exercises", ["prof" => $pid]));
}

function action_copy_global_exercise(): never
{
    $u = require_role(["admin", "professional"]);
    $pid =
        $u["role"] === "professional"
            ? professional_id($u)
            : (int) ($_POST["professional_id"] ?? 0);
    $globalId = (int) ($_POST["global_exercise_id"] ?? 0);
    $valid = db()->prepare(
        "SELECT id FROM professionals WHERE id=? AND tenant_id=?",
    );
    $valid->execute([$pid, $u["tenant_id"]]);
    if (!$valid->fetchColumn()) {
        throw new RuntimeException("Profissional invalido.");
    }
    $q = db()->prepare(
        "SELECT * FROM global_exercises WHERE id=? AND active=1",
    );
    $q->execute([$globalId]);
    $exercise = $q->fetch();
    if (!$exercise) {
        throw new RuntimeException("Exercicio padrao nao encontrado.");
    }
    db()
        ->prepare(
            'INSERT INTO exercise_catalog(tenant_id,professional_id,name,muscle_group,equipment,instructions,image_path,video_url) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE
muscle_group=VALUES(muscle_group),
equipment=VALUES(equipment),
instructions=VALUES(instructions),
image_path=VALUES(image_path),
video_url=VALUES(video_url)',
        )
        ->execute([
            $u["tenant_id"],
            $pid,
            $exercise["name"],
            $exercise["muscle_group"],
            $exercise["equipment"],
            $exercise["instructions"],
            $exercise["image_path"],
            $exercise["video_url"],
        ]);
    flash(
        "success",
        "Exercicio adicionado ao seu catalogo. Agora voce pode personaliza-lo.",
    );
    redirect(url("exercises", ["prof" => $pid]));
}

function action_copy_all_global_exercises(): never
{
    $u = require_role(["admin", "professional"]);
    $pid =
        $u["role"] === "professional"
            ? professional_id($u)
            : (int) ($_POST["professional_id"] ?? 0);
    $valid = db()->prepare(
        "SELECT id FROM professionals WHERE id=? AND tenant_id=?",
    );
    $valid->execute([$pid, $u["tenant_id"]]);
    if (!$valid->fetchColumn()) {
        throw new RuntimeException("Profissional inválido.");
    }
    $sql = "INSERT INTO exercise_catalog(tenant_id,professional_id,name,muscle_group,equipment,instructions,image_path,video_url)
          SELECT ?,?,g.name,g.muscle_group,g.equipment,g.instructions,g.image_path,g.video_url
          FROM global_exercises g
          WHERE g.active=1
          ON DUPLICATE KEY UPDATE
muscle_group=VALUES(muscle_group),
equipment=VALUES(equipment),
instructions=VALUES(instructions),
image_path=VALUES(image_path),
video_url=VALUES(video_url)";
    db()
        ->prepare($sql)
        ->execute([$u["tenant_id"], $pid]);
    flash(
        "success",
        "Biblioteca completa adicionada ao catálogo do profissional.",
    );
    redirect(url("exercises", ["prof" => $pid]));
}

function action_delete_exercise(): never
{
    $u = require_role(["admin", "professional"]);
    $id = (int) $_POST["id"];
    $pid =
        $u["role"] === "professional"
            ? professional_id($u)
            : (int) $_POST["professional_id"];
    db()
        ->prepare(
            "UPDATE exercise_catalog e JOIN professionals p ON p.id=e.professional_id SET e.active=0 WHERE e.id=? AND e.professional_id=? AND p.tenant_id=?",
        )
        ->execute([$id, $pid, $u["tenant_id"]]);
    audit("exercise.archived", "exercise", $id);
    flash("success", "Exercicio removido do catalogo ativo.");
    redirect(url("exercises", ["prof" => $pid]));
}

// -----------------------------------------------------------------------------
// Fichas de treino e planos alimentares do profissional.
// -----------------------------------------------------------------------------
function action_create_workout(): never
{
    $u = require_role(["admin", "professional"]);
    $sid = (int) $_POST["student_id"];
    $s = get_scoped_student($sid, $u);
    $q = db()->prepare(
        "SELECT COALESCE(MAX(version),0)+1 FROM workout_sheets WHERE student_id=? AND name=?",
    );
    $q->execute([$sid, $_POST["name"]]);
    $version = (int) $q->fetchColumn();
    db()
        ->prepare(
            "INSERT INTO workout_sheets(student_id,professional_id,name,objective,weekly_frequency,version) VALUES(?,?,?,?,?,?)",
        )
        ->execute([
            $sid,
            $s["professional_id"],
            $_POST["name"],
            $_POST["objective"] ?: null,
            $_POST["weekly_frequency"] ?: null,
            $version,
        ]);
    $id = (int) db()->lastInsertId();
    flash("success", "Ficha criada. Adicione os exercicios do catalogo.");
    redirect(
        url("student", [
            "id" => $sid,
            "tab" => "workouts",
            "edit_workout" => $id,
        ]),
    );
}

function action_generate_workout_draft(): never
{
    $u = require_role("professional");
    $sid = (int) ($_POST["student_id"] ?? 0);
    $student = get_scoped_student($sid, $u);
    $pid = (int) $student["professional_id"];
    $aq = db()->prepare(
        "SELECT objective,training_days_per_week,training_minutes_per_session,injuries_surgeries FROM student_anamneses WHERE student_id=?",
    );
    $aq->execute([$sid]);
    $a = $aq->fetch();
    if (!$a) {
        throw new RuntimeException(
            "O aluno precisa preencher a anamnese antes de gerar a ficha.",
        );
    }
    $days = max(1, min(7, (int) ($a["training_days_per_week"] ?: 3)));
    $minutes = max(
        20,
        min(180, (int) ($a["training_minutes_per_session"] ?: 50)),
    );
    $limit = max(4, min(10, (int) floor($minutes / 8)));
    $objective = trim(
        (string) ($a["objective"] ?:
        $student["objective"] ?:
        "Condicionamento geral"),
    );
    $needle = mb_strtolower($objective);
    $repetitions = str_contains($needle, "força")
        ? "6 a 8"
        : (str_contains($needle, "hipertrof") || str_contains($needle, "massa")
            ? "8 a 12"
            : "12 a 15");
    $rest = str_contains($needle, "força") ? 120 : 60;
    $q = db()->prepare(
        "SELECT * FROM exercise_catalog WHERE professional_id=? AND active=1 ORDER BY muscle_group,name",
    );
    $q->execute([$pid]);
    $catalog = $q->fetchAll();
    if (count($catalog) < 4) {
        throw new RuntimeException(
            "Adicione pelo menos quatro exercícios ao catálogo antes de gerar a ficha.",
        );
    }
    $splits = match (true) {
        $days === 1 => [
            [
                "Corpo inteiro",
                ["quadr", "peit", "costa", "glút", "posterior", "ombro", "abd"],
            ],
        ],
        $days === 2 => [
            [
                "Membros superiores",
                ["peit", "costa", "ombro", "bíce", "bice", "tríce", "trice"],
            ],
            [
                "Membros inferiores",
                ["quadr", "glút", "posterior", "panturr", "adutor", "abdutor"],
            ],
        ],
        $days === 3 => [
            ["Empurrar", ["peit", "ombro", "tríce", "trice"]],
            ["Puxar", ["costa", "dors", "bíce", "bice", "trapé", "trape"]],
            [
                "Pernas",
                ["quadr", "glút", "posterior", "panturr", "adutor", "abdutor"],
            ],
        ],
        $days === 4 => [
            ["Superiores A", ["peit", "ombro", "tríce", "trice"]],
            ["Inferiores A", ["quadr", "panturr", "adutor"]],
            [
                "Superiores B",
                ["costa", "dors", "bíce", "bice", "trapé", "trape"],
            ],
            ["Inferiores B", ["glút", "posterior", "abdutor"]],
        ],
        default => [
            ["Peitoral e tríceps", ["peit", "tríce", "trice"]],
            [
                "Costas e bíceps",
                ["costa", "dors", "bíce", "bice", "trapé", "trape"],
            ],
            ["Quadríceps e panturrilhas", ["quadr", "panturr", "adutor"]],
            ["Ombros e core", ["ombro", "delt", "abd", "core"]],
            ["Posteriores e glúteos", ["posterior", "glút", "abdutor"]],
        ],
    };
    while (count($splits) < $days) {
        $splits[] = [
            "Condicionamento e core",
            ["abd", "core", "cardio", "funcional"],
        ];
    }
    $reviewNote = null;
    $sheetInsert = db()->prepare(
        "INSERT INTO workout_sheets(student_id,professional_id,name,objective,weekly_frequency,version,status) VALUES(?,?,?,?,1,?,'draft')",
    );
    $itemInsert = db()->prepare(
        "INSERT INTO workout_items(workout_sheet_id,exercise_id,sets_count,repetitions,suggested_load,rest_seconds,notes,sort_order) VALUES(?,?,?,?,?,?,?,?)",
    );
    $versionQuery = db()->prepare(
        "SELECT COALESCE(MAX(version),0)+1 FROM workout_sheets WHERE student_id=? AND name=?",
    );
    $created = [];
    $catalogCount = count($catalog);
    db()->beginTransaction();
    try {
        for ($day = 0; $day < $days; $day++) {
            [$focus, $keywords] = $splits[$day];
            $chosen = [];
            $used = [];
            foreach ($catalog as $exercise) {
                $group = mb_strtolower((string) $exercise["muscle_group"]);
                foreach ($keywords as $keyword) {
                    if (
                        mb_stripos($group, $keyword) !== false &&
                        !isset($used[$exercise["id"]])
                    ) {
                        $chosen[] = $exercise;
                        $used[$exercise["id"]] = true;
                        break;
                    }
                }
                if (count($chosen) >= $limit) {
                    break;
                }
            }
            for (
                $offset = 0;
                count($chosen) < $limit && $offset < $catalogCount;
                $offset++
            ) {
                $exercise = $catalog[($day * $limit + $offset) % $catalogCount];
                if (!isset($used[$exercise["id"]])) {
                    $chosen[] = $exercise;
                    $used[$exercise["id"]] = true;
                }
            }
            $letter = chr(65 + $day);
            $name = "Treino " . $letter;
            $versionQuery->execute([$sid, $name]);
            $version = (int) $versionQuery->fetchColumn();
            $sheetInsert->execute([
                $sid,
                $pid,
                $name,
                $objective .
                " - " .
                $focus .
                " - duração estimada de " .
                $minutes .
                " min",
                $version,
            ]);
            $sheet = (int) db()->lastInsertId();
            $created[] = $sheet;
            foreach ($chosen as $position => $exercise) {
                $itemInsert->execute([
                    $sheet,
                    $exercise["id"],
                    3,
                    $repetitions,
                    null,
                    $rest,
                    $reviewNote,
                    $position + 1,
                ]);
            }
        }
        db()->commit();
    } catch (Throwable $error) {
        db()->rollBack();
        throw $error;
    }
    audit("workout.program.generated", "student", $sid, [
        "sheet_ids" => $created,
        "days" => $days,
        "minutes_per_session" => $minutes,
        "exercises_per_sheet" => $limit,
    ]);
    flash(
        "success",
        "Programa criado com " .
            $days .
            " fichas, de Treino A até Treino " .
            chr(64 + $days) .
            ". Revise cada ficha antes de publicar.",
    );
    redirect(url("student", ["id" => $sid, "tab" => "workouts"]));
}

function action_save_workout_item(): never
{
    $u = require_role(["admin", "professional"]);
    $sheet = (int) $_POST["workout_sheet_id"];
    $stmt = db()->prepare(
        "SELECT student_id,professional_id FROM workout_sheets WHERE id=?",
    );
    $stmt->execute([$sheet]);
    $ws = $stmt->fetch();
    if (!$ws) {
        throw new RuntimeException("Ficha invalida.");
    }
    $sid = (int) $ws["student_id"];
    get_scoped_student($sid, $u);
    $check = db()->prepare(
        "SELECT id FROM exercise_catalog WHERE id=? AND professional_id=? AND active=1",
    );
    $check->execute([$_POST["exercise_id"], $ws["professional_id"]]);
    if (!$check->fetchColumn()) {
        throw new RuntimeException("Exercicio invalido.");
    }
    $item = (int) ($_POST["item_id"] ?? 0);
    if ($item) {
        db()
            ->prepare(
                "UPDATE workout_items SET exercise_id=?,sets_count=?,repetitions=?,suggested_load=?,rest_seconds=?,notes=? WHERE id=? AND workout_sheet_id=?",
            )
            ->execute([
                $_POST["exercise_id"],
                $_POST["sets_count"] ?: null,
                $_POST["repetitions"] ?: null,
                $_POST["suggested_load"] ?: null,
                $_POST["rest_seconds"] ?: null,
                $_POST["notes"] ?: null,
                $item,
                $sheet,
            ]);
    } else {
        db()
            ->prepare(
                "INSERT INTO workout_items(workout_sheet_id,exercise_id,sets_count,repetitions,suggested_load,rest_seconds,notes,sort_order) VALUES(?,?,?,?,?,?,?,?)",
            )
            ->execute([
                $sheet,
                $_POST["exercise_id"],
                $_POST["sets_count"] ?: null,
                $_POST["repetitions"] ?: null,
                $_POST["suggested_load"] ?: null,
                $_POST["rest_seconds"] ?: null,
                $_POST["notes"] ?: null,
                $_POST["sort_order"] ?: 0,
            ]);
    }
    flash(
        "success",
        $item ? "Exercicio atualizado." : "Exercicio inserido na ficha.",
    );
    redirect(
        url("student", [
            "id" => $sid,
            "tab" => "workouts",
            "edit_workout" => $sheet,
        ]),
    );
}

function action_delete_workout_item(): never
{
    $u = require_role(["admin", "professional"]);
    $sheet = (int) $_POST["workout_sheet_id"];
    $stmt = db()->prepare("SELECT student_id FROM workout_sheets WHERE id=?");
    $stmt->execute([$sheet]);
    $sid = (int) $stmt->fetchColumn();
    get_scoped_student($sid, $u);
    db()
        ->prepare("DELETE FROM workout_items WHERE id=? AND workout_sheet_id=?")
        ->execute([(int) $_POST["item_id"], $sheet]);
    flash("success", "Exercicio removido da ficha.");
    redirect(
        url("student", [
            "id" => $sid,
            "tab" => "workouts",
            "edit_workout" => $sheet,
        ]),
    );
}

function action_publish_workout(): never
{
    $u = require_role(["admin", "professional"]);
    $id = (int) $_POST["id"];
    $stmt = db()->prepare(
        "SELECT student_id,name FROM workout_sheets WHERE id=?",
    );
    $stmt->execute([$id]);
    $ws = $stmt->fetch();
    if (!$ws) {
        throw new RuntimeException("Ficha invalida.");
    }
    $sid = (int) $ws["student_id"];
    get_scoped_student($sid, $u);
    db()->beginTransaction();
    db()
        ->prepare(
            "UPDATE workout_sheets SET status='archived' WHERE student_id=? AND name=? AND status='published' AND id<>?",
        )
        ->execute([$sid, $ws["name"], $id]);
    db()
        ->prepare(
            "UPDATE workout_sheets SET status='published',published_at=NOW() WHERE id=?",
        )
        ->execute([$id]);
    db()->commit();
    flash("success", "Treino publicado para o aluno.");
    redirect(url("student", ["id" => $sid, "tab" => "workouts"]));
}

function action_delete_workout(): never
{
    $u = require_role(["admin", "professional"]);
    $id = (int) $_POST["id"];
    $stmt = db()->prepare("SELECT student_id FROM workout_sheets WHERE id=?");
    $stmt->execute([$id]);
    $sid = (int) $stmt->fetchColumn();
    get_scoped_student($sid, $u);
    db()
        ->prepare("UPDATE workout_sheets SET status='archived' WHERE id=?")
        ->execute([$id]);
    flash("success", "Ficha arquivada.");
    redirect(url("student", ["id" => $sid, "tab" => "workouts"]));
}

function action_create_food_plan(): never
{
    $u = require_role(["admin", "professional"]);
    $sid = (int) $_POST["student_id"];
    $s = get_scoped_student($sid, $u);
    $q = db()->prepare(
        "SELECT COALESCE(MAX(version),0)+1 FROM food_plans WHERE student_id=?",
    );
    $q->execute([$sid]);
    $version = (int) $q->fetchColumn();
    db()
        ->prepare(
            "INSERT INTO food_plans(student_id,professional_id,title,objective,general_guidance,version) VALUES(?,?,?,?,?,?)",
        )
        ->execute([
            $sid,
            $s["professional_id"],
            $_POST["title"],
            $_POST["objective"] ?: null,
            $_POST["general_guidance"] ?: null,
            $version,
        ]);
    $id = (int) db()->lastInsertId();
    flash("success", "Plano alimentar criado. Edite as refeicoes nesta ficha.");
    redirect(
        url("student", ["id" => $sid, "tab" => "food", "edit_plan" => $id]),
    );
}

function action_save_meal(): never
{
    $u = require_role(["admin", "professional"]);
    $plan = (int) $_POST["food_plan_id"];
    $stmt = db()->prepare("SELECT student_id FROM food_plans WHERE id=?");
    $stmt->execute([$plan]);
    $sid = (int) $stmt->fetchColumn();
    get_scoped_student($sid, $u);
    $meal = (int) ($_POST["meal_id"] ?? 0);
    if ($meal) {
        db()
            ->prepare(
                "UPDATE meals SET meal_time=?,name=?,foods=?,substitutions=? WHERE id=? AND food_plan_id=?",
            )
            ->execute([
                $_POST["meal_time"] ?: null,
                $_POST["name"],
                $_POST["foods"],
                $_POST["substitutions"] ?: null,
                $meal,
                $plan,
            ]);
    } else {
        db()
            ->prepare(
                "INSERT INTO meals(food_plan_id,meal_time,name,foods,substitutions,sort_order) VALUES(?,?,?,?,?,?)",
            )
            ->execute([
                $plan,
                $_POST["meal_time"] ?: null,
                $_POST["name"],
                $_POST["foods"],
                $_POST["substitutions"] ?: null,
                $_POST["sort_order"] ?: 0,
            ]);
    }
    flash("success", $meal ? "Refeicao atualizada." : "Refeicao adicionada.");
    redirect(
        url("student", ["id" => $sid, "tab" => "food", "edit_plan" => $plan]),
    );
}

function action_delete_meal(): never
{
    $u = require_role(["admin", "professional"]);
    $plan = (int) $_POST["food_plan_id"];
    $stmt = db()->prepare("SELECT student_id FROM food_plans WHERE id=?");
    $stmt->execute([$plan]);
    $sid = (int) $stmt->fetchColumn();
    get_scoped_student($sid, $u);
    db()
        ->prepare("DELETE FROM meals WHERE id=? AND food_plan_id=?")
        ->execute([(int) $_POST["meal_id"], $plan]);
    flash("success", "Refeicao removida.");
    redirect(
        url("student", ["id" => $sid, "tab" => "food", "edit_plan" => $plan]),
    );
}

function action_publish_food_plan(): never
{
    $u = require_role(["admin", "professional"]);
    $id = (int) $_POST["id"];
    $stmt = db()->prepare("SELECT student_id FROM food_plans WHERE id=?");
    $stmt->execute([$id]);
    $sid = (int) $stmt->fetchColumn();
    get_scoped_student($sid, $u);
    db()->beginTransaction();
    db()
        ->prepare(
            "UPDATE food_plans SET status='archived' WHERE student_id=? AND status='published' AND id<>?",
        )
        ->execute([$sid, $id]);
    db()
        ->prepare(
            "UPDATE food_plans SET status='published',published_at=NOW() WHERE id=?",
        )
        ->execute([$id]);
    db()->commit();
    flash("success", "Dieta publicada para o aluno.");
    redirect(url("student", ["id" => $sid, "tab" => "food"]));
}

// -----------------------------------------------------------------------------
// Ações executadas diretamente pelo aluno.
// -----------------------------------------------------------------------------
/** Salva medidas, avaliação e fotos de evolução do próprio aluno. */
function action_update_weight(): never
{
    $u = require_role("student");
    $stmt = db()->prepare("SELECT id FROM students WHERE user_id=?");
    $stmt->execute([$u["id"]]);
    $sid = (int) $stmt->fetchColumn();
    $number = static fn(string $field): ?float => trim(
        (string) ($_POST[$field] ?? ""),
    ) === ""
        ? null
        : (float) str_replace(",", ".", (string) $_POST[$field]);
    $weight = $number("weight_kg");
    $waist = $number("waist_cm");
    $hip = $number("hip_cm");
    if ($weight === null || $weight < 20 || $weight > 400) {
        throw new RuntimeException("Informe um peso válido.");
    }
    if ($waist !== null && ($waist < 20 || $waist > 300)) {
        throw new RuntimeException("Informe uma cintura válida.");
    }
    if ($hip !== null && ($hip < 20 || $hip > 300)) {
        throw new RuntimeException("Informe um quadril válido.");
    }
    $photos = [];
    foreach (
        [
            "progress_front" => "front",
            "progress_side" => "side",
            "progress_back" => "back",
        ]
        as $field => $type
    ) {
        $path = upload_image($field, "progress");
        if ($path) {
            $photos[] = [$path, $type];
        }
    }
    db()->beginTransaction();
    db()
        ->prepare("UPDATE students SET current_weight_kg=? WHERE id=?")
        ->execute([$weight, $sid]);
    db()
        ->prepare(
            "INSERT INTO assessments(student_id,weight_kg,waist_cm,hip_cm,notes) VALUES(?,?,?,?,?)",
        )
        ->execute([
            $sid,
            $weight,
            $waist,
            $hip,
            trim((string) ($_POST["notes"] ?? "")) ?: "Atualizado pelo aluno",
        ]);
    $assessmentId = (int) db()->lastInsertId();
    foreach ($photos as [$path, $type]) {
        db()
            ->prepare(
                "INSERT INTO progress_photos(student_id,assessment_id,image_path,photo_type) VALUES(?,?,?,?)",
            )
            ->execute([$sid, $assessmentId, $path, $type]);
    }
    db()->commit();
    flash("success", "Evolução registrada com segurança.");
    redirect(url("my-progress"));
}

function action_save_anamnesis(): never
{
    $u = require_role("student");
    $stmt = db()->prepare(
        "SELECT id,service_type FROM students WHERE user_id=?",
    );
    $stmt->execute([$u["id"]]);
    $student = $stmt->fetch();
    $sid = (int) ($student["id"] ?? 0);
    if (!$sid) {
        throw new RuntimeException("Aluno não encontrado.");
    }
    $birthDate = trim((string) ($_POST["birth_date"] ?? ""));
    $height = height_to_cm($_POST["height_m"] ?? null);
    $weight = (float) str_replace(
        ",",
        ".",
        (string) ($_POST["weight_kg"] ?? "0"),
    );
    $objective = trim((string) ($_POST["objective"] ?? ""));
    $routine = trim((string) ($_POST["daily_routine"] ?? ""));
    if ($birthDate === "" || $height === null) {
        throw new RuntimeException("Preencha nascimento e altura.");
    }
    if ($weight < 20 || $weight > 400) {
        throw new RuntimeException("Informe um peso válido.");
    }
    if ($routine === "") {
        throw new RuntimeException("Conte como é sua rotina.");
    }
    if ($objective === "") {
        throw new RuntimeException("Informe seu objetivo.");
    }
    $hasAllergies = (int) ($_POST["has_allergies"] ?? 0);
    $usedErgogenics = (int) ($_POST["used_ergogenics"] ?? 0);
    $mealsAtWork = (string) ($_POST["meals_at_work"] ?? "");
    if (!in_array($mealsAtWork, ["0", "1"], true)) {
        throw new RuntimeException(
            "Informe se consegue fazer refeições no trabalho.",
        );
    }
    $hasTraining = $student["service_type"] !== "nutrition";
    $trainingDays = $hasTraining
        ? (int) ($_POST["training_days_per_week"] ?? 0)
        : null;
    $trainingMinutes = $hasTraining
        ? (int) ($_POST["training_minutes_per_session"] ?? 0)
        : null;
    if ($hasTraining && ($trainingDays < 1 || $trainingDays > 7)) {
        throw new RuntimeException(
            "Informe quantos dias por semana deseja treinar.",
        );
    }
    if ($hasTraining && ($trainingMinutes < 20 || $trainingMinutes > 180)) {
        throw new RuntimeException(
            "O tempo de treino deve ficar entre 20 e 180 minutos.",
        );
    }
    db()->beginTransaction();
    db()
        ->prepare(
            "UPDATE students SET birth_date=?,height_cm=?,current_weight_kg=?,objective=? WHERE id=?",
        )
        ->execute([$birthDate, $height, $weight, $objective, $sid]);
    db()
        ->prepare(
            "INSERT INTO student_anamneses(student_id,daily_routine,training_days_per_week,training_minutes_per_session,meals_at_work,diseases,controlled_medications,injuries_surgeries,has_allergies,allergies,used_ergogenics,ergogenics,objective,submitted_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE daily_routine=VALUES(daily_routine),training_days_per_week=VALUES(training_days_per_week),training_minutes_per_session=VALUES(training_minutes_per_session),meals_at_work=VALUES(meals_at_work),diseases=VALUES(diseases),controlled_medications=VALUES(controlled_medications),injuries_surgeries=VALUES(injuries_surgeries),has_allergies=VALUES(has_allergies),allergies=VALUES(allergies),used_ergogenics=VALUES(used_ergogenics),ergogenics=VALUES(ergogenics),objective=VALUES(objective),submitted_at=NOW()",
        )
        ->execute([
            $sid,
            $routine,
            $trainingDays,
            $trainingMinutes,
            (int) $mealsAtWork,
            trim((string) ($_POST["diseases"] ?? "")) ?: null,
            trim((string) ($_POST["controlled_medications"] ?? "")) ?: null,
            trim((string) ($_POST["injuries_surgeries"] ?? "")) ?: null,
            $hasAllergies,
            $hasAllergies
                ? (trim((string) ($_POST["allergies"] ?? "")) ?:
                null)
                : null,
            $usedErgogenics,
            $usedErgogenics
                ? (trim((string) ($_POST["ergogenics"] ?? "")) ?:
                null)
                : null,
            $objective,
        ]);
    db()->commit();
    audit("anamnesis.submitted", "student", $sid);
    flash("success", "Anamnese enviada ao seu profissional.");
    redirect(url("my-anamnesis"));
}

function action_send_professional_message(): never
{
    $u = require_role("student");
    $message = trim((string) ($_POST["message"] ?? ""));
    if (mb_strlen($message) < 3 || mb_strlen($message) > 1500) {
        throw new RuntimeException(
            "Escreva uma mensagem entre 3 e 1.500 caracteres.",
        );
    }
    $stmt = db()->prepare(
        "SELECT id,professional_id,tenant_id FROM students WHERE user_id=?",
    );
    $stmt->execute([$u["id"]]);
    $student = $stmt->fetch();
    if (!$student) {
        throw new RuntimeException("Aluno não encontrado.");
    }
    db()
        ->prepare(
            "INSERT INTO student_messages(tenant_id,professional_id,student_id,message) VALUES(?,?,?,?)",
        )
        ->execute([
            $student["tenant_id"],
            $student["professional_id"],
            $student["id"],
            $message,
        ]);
    audit("student.message.sent", "student", (int) $student["id"]);
    flash("success", "Mensagem enviada ao seu profissional.");
    redirect(back_url());
}

function action_reply_student_message(): never
{
    $u = require_role("professional");
    $pid = professional_id($u);
    $id = (int) ($_POST["message_id"] ?? 0);
    $reply = trim((string) ($_POST["reply_message"] ?? ""));
    if (mb_strlen($reply) < 2 || mb_strlen($reply) > 1500) {
        throw new RuntimeException(
            "Escreva uma resposta entre 2 e 1.500 caracteres.",
        );
    }
    $q = db()->prepare(
        "UPDATE student_messages SET reply_message=?,replied_at=NOW(),read_at=COALESCE(read_at,NOW()) WHERE id=? AND professional_id=?",
    );
    $q->execute([$reply, $id, $pid]);
    if (!$q->rowCount()) {
        throw new RuntimeException("Mensagem não encontrada.");
    }
    audit("student.message.replied", "student_message", $id);
    flash("success", "Resposta enviada ao aluno.");
    redirect(url("dashboard"));
}

function action_start_workout(): never
{
    $u = require_role("student");
    $stmt = db()->prepare("SELECT id FROM students WHERE user_id=?");
    $stmt->execute([$u["id"]]);
    $sid = (int) $stmt->fetchColumn();
    $sheet = (int) $_POST["workout_sheet_id"];
    $check = db()->prepare(
        "SELECT id FROM workout_sheets WHERE id=? AND student_id=? AND status='published'",
    );
    $check->execute([$sheet, $sid]);
    if (!$check->fetchColumn()) {
        throw new RuntimeException("Treino indisponivel.");
    }
    $open = db()->prepare(
        "SELECT id FROM workout_sessions WHERE workout_sheet_id=? AND student_id=? AND finished_at IS NULL ORDER BY started_at DESC LIMIT 1",
    );
    $open->execute([$sheet, $sid]);
    $openId = (int) $open->fetchColumn();
    if ($openId) {
        redirect(url("session", ["id" => $openId]));
    }
    db()
        ->prepare(
            "INSERT INTO workout_sessions(workout_sheet_id,student_id) VALUES(?,?)",
        )
        ->execute([$sheet, $sid]);
    redirect(url("session", ["id" => (int) db()->lastInsertId()]));
}

function action_control_workout(): never
{
    $u = require_role("student");
    $id = (int) ($_POST["session_id"] ?? 0);
    $command = (string) ($_POST["command"] ?? "");
    if (!in_array($command, ["start", "pause", "resume"], true)) {
        throw new RuntimeException("Comando de treino inválido.");
    }
    $stmt = db()->prepare(
        "SELECT ws.id,ws.timer_started_at,ws.paused_at FROM workout_sessions ws JOIN students s ON s.id=ws.student_id WHERE ws.id=? AND s.user_id=? AND ws.finished_at IS NULL",
    );
    $stmt->execute([$id, $u["id"]]);
    $session = $stmt->fetch();
    if (!$session) {
        throw new RuntimeException("Sessão de treino inválida.");
    }
    if ($command === "start") {
        if ($session["timer_started_at"]) {
            throw new RuntimeException("O treino já foi iniciado.");
        }
        db()
            ->prepare(
                "UPDATE workout_sessions SET timer_started_at=NOW(),paused_at=NULL,paused_seconds=0 WHERE id=?",
            )
            ->execute([$id]);
        $state = "running";
    } elseif ($command === "pause") {
        if (!$session["timer_started_at"] || $session["paused_at"]) {
            throw new RuntimeException("Não é possível pausar agora.");
        }
        db()
            ->prepare("UPDATE workout_sessions SET paused_at=NOW() WHERE id=?")
            ->execute([$id]);
        $state = "paused";
    } else {
        if (!$session["timer_started_at"] || !$session["paused_at"]) {
            throw new RuntimeException("O treino não está pausado.");
        }
        db()
            ->prepare(
                "UPDATE workout_sessions SET paused_seconds=paused_seconds+TIMESTAMPDIFF(SECOND,paused_at,NOW()),paused_at=NULL WHERE id=?",
            )
            ->execute([$id]);
        $state = "running";
    }
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode([
        "ok" => true,
        "state" => $state,
        "server_time" => time(),
    ]);
    exit();
}

function action_toggle_workout_pause(): never
{
    $u = require_role("student");
    $id = (int) ($_POST["session_id"] ?? 0);
    $stmt = db()->prepare(
        "SELECT ws.id,ws.paused_at FROM workout_sessions ws JOIN students s ON s.id=ws.student_id WHERE ws.id=? AND s.user_id=? AND ws.finished_at IS NULL",
    );
    $stmt->execute([$id, $u["id"]]);
    $session = $stmt->fetch();
    if (!$session) {
        throw new RuntimeException("Sessão de treino inválida.");
    }
    if ($session["paused_at"]) {
        db()
            ->prepare(
                "UPDATE workout_sessions SET paused_seconds=paused_seconds+TIMESTAMPDIFF(SECOND,paused_at,NOW()),paused_at=NULL WHERE id=?",
            )
            ->execute([$id]);
        $state = "running";
    } else {
        db()
            ->prepare("UPDATE workout_sessions SET paused_at=NOW() WHERE id=?")
            ->execute([$id]);
        $state = "paused";
    }
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["ok" => true, "state" => $state]);
    exit();
}

function action_update_workout_exercise(): never
{
    $u = require_role("student");
    $sessionId = (int) ($_POST["session_id"] ?? 0);
    $itemId = (int) ($_POST["workout_item_id"] ?? 0);
    $state = (string) ($_POST["state"] ?? "started");
    $performedLoad = trim((string) ($_POST["performed_load"] ?? ""));
    $performedRepetitions = trim(
        (string) ($_POST["performed_repetitions"] ?? ""),
    );
    if (
        mb_strlen($performedLoad) > 40 ||
        mb_strlen($performedRepetitions) > 40
    ) {
        throw new RuntimeException("Carga ou repetições inválidas.");
    }
    if (!in_array($state, ["started", "completed"], true)) {
        throw new RuntimeException("Status inválido.");
    }
    $timerCheck = db()->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='workout_sessions' AND COLUMN_NAME='timer_started_at'",
    );
    if ((int) $timerCheck->fetchColumn() === 1) {
        $timerState = db()->prepare(
            "SELECT timer_started_at,paused_at FROM workout_sessions WHERE id=?",
        );
        $timerState->execute([$sessionId]);
        $timerSession = $timerState->fetch();
        if (!$timerSession || !$timerSession["timer_started_at"]) {
            throw new RuntimeException(
                "Inicie o treino antes de atualizar o exercício.",
            );
        }
        if ($timerSession["paused_at"]) {
            throw new RuntimeException(
                "Continue o treino antes de atualizar o exercício.",
            );
        }
    }
    $check = db()->prepare(
        "SELECT ws.id FROM workout_sessions ws JOIN students s ON s.id=ws.student_id JOIN workout_items wi ON wi.workout_sheet_id=ws.workout_sheet_id WHERE ws.id=? AND wi.id=? AND s.user_id=? AND ws.finished_at IS NULL",
    );
    $check->execute([$sessionId, $itemId, $u["id"]]);
    if (!$check->fetchColumn()) {
        throw new RuntimeException("Exercício ou sessão inválida.");
    }
    $find = db()->prepare(
        "SELECT id,started_at,completed_at FROM workout_logs WHERE session_id=? AND workout_item_id=?",
    );
    $find->execute([$sessionId, $itemId]);
    $log = $find->fetch();
    if (!$log) {
        db()
            ->prepare(
                "INSERT INTO workout_logs(session_id,workout_item_id,started_at,completed_at,performed_load,performed_repetitions,completed) VALUES(?,?,NOW()," .
                    ($state === "completed" ? "NOW()" : "NULL") .
                    ",?,?,?)",
            )
            ->execute([
                $sessionId,
                $itemId,
                $performedLoad ?: null,
                $performedRepetitions ?: null,
                $state === "completed" ? 1 : 0,
            ]);
    } elseif ($state === "completed") {
        db()
            ->prepare(
                "UPDATE workout_logs SET started_at=COALESCE(started_at,NOW()),completed_at=COALESCE(completed_at,NOW()),performed_load=?,performed_repetitions=?,completed=1 WHERE id=?",
            )
            ->execute([
                $performedLoad ?: null,
                $performedRepetitions ?: null,
                $log["id"],
            ]);
    }
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["ok" => true, "state" => $state, "time" => date("H:i")]);
    exit();
}

function action_finish_workout(): never
{
    $u = require_role("student");
    $id = (int) $_POST["session_id"];
    $columnCheck = db()->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='workout_sessions' AND COLUMN_NAME IN ('paused_at','paused_seconds','timer_started_at')",
    );
    $timerReady = (int) $columnCheck->fetchColumn() === 3;
    $fields = $timerReady
        ? "ws.timer_started_at,ws.paused_at,ws.paused_seconds"
        : "ws.started_at";
    $stmt = db()->prepare(
        "SELECT $fields FROM workout_sessions ws JOIN students s ON s.id=ws.student_id WHERE ws.id=? AND s.user_id=? AND ws.finished_at IS NULL",
    );
    $stmt->execute([$id, $u["id"]]);
    $session = $stmt->fetch();
    if (!$session) {
        throw new RuntimeException("Sessao invalida.");
    }
    if ($timerReady && !$session["timer_started_at"]) {
        throw new RuntimeException("Inicie o treino antes de finalizar.");
    }
    $startField = $timerReady ? "timer_started_at" : "started_at";
    $paused = $timerReady ? (int) $session["paused_seconds"] : 0;
    if ($timerReady && $session["paused_at"]) {
        $paused += max(0, time() - strtotime($session["paused_at"]));
    }
    $duration = max(0, time() - strtotime($session[$startField]) - $paused);
    if ($timerReady) {
        db()
            ->prepare(
                "UPDATE workout_sessions SET finished_at=NOW(),duration_seconds=?,paused_seconds=?,paused_at=NULL,notes=? WHERE id=?",
            )
            ->execute([$duration, $paused, $_POST["notes"] ?: null, $id]);
    } else {
        db()
            ->prepare(
                "UPDATE workout_sessions SET finished_at=NOW(),duration_seconds=?,notes=? WHERE id=?",
            )
            ->execute([$duration, $_POST["notes"] ?: null, $id]);
    }
    flash("success", "Treino finalizado. Parabens pelo progresso!");
    redirect(url("my-workouts"));
}
