<?php
declare(strict_types=1);

function asaas_enabled(): bool
{
    return trim((string)env('ASAAS_API_KEY', '')) !== '' && trim((string)env('ASAAS_WEBHOOK_TOKEN', '')) !== '';
}

function asaas_base_url(): string
{
    return env('ASAAS_ENV', 'production') === 'sandbox' ? 'https://api-sandbox.asaas.com/v3' : 'https://api.asaas.com/v3';
}

function asaas_request(string $method, string $path, ?array $payload = null): array
{
    if (!asaas_enabled()) throw new RuntimeException('Integração Asaas ainda não configurada no servidor.');
    $ch=curl_init(asaas_base_url().$path);
    $headers=['Content-Type: application/json','Accept: application/json','access_token: '.(string)env('ASAAS_API_KEY'),'User-Agent: Kinsman-Pulse/1.0'];
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>30]);
    if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    $body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);
    if($body===false||$error!=='')throw new RuntimeException('Falha de comunicação com a Asaas.');
    $data=json_decode((string)$body,true);
    if($status<200||$status>=300){$message=$data['errors'][0]['description']??'A Asaas recusou a solicitação.';throw new RuntimeException((string)$message);}
    return is_array($data)?$data:[];
}

function asaas_checkout_url(string $id): string
{
    $base=env('ASAAS_ENV','production')==='sandbox'?'https://sandbox.asaas.com/checkoutSession/show?id=':'https://asaas.com/checkoutSession/show?id=';
    return $base.rawurlencode($id);
}

function qualify_referral_reward(int $referredProfessionalId): void
{
    if(!db_table_exists('referral_rewards'))return;
    $q=db()->prepare("UPDATE referral_rewards SET status='available',qualified_at=NOW() WHERE referred_professional_id=? AND status='pending'");
    $q->execute([$referredProfessionalId]);
    $q=db()->prepare("SELECT referrer_professional_id FROM referral_rewards WHERE referred_professional_id=? AND status='available' LIMIT 1");$q->execute([$referredProfessionalId]);$referrerId=(int)$q->fetchColumn();
    if($referrerId)apply_referral_rewards($referrerId);
}

function apply_referral_rewards(int $referrerProfessionalId): void
{
    $q=db()->prepare("SELECT id,benefit_due_date FROM referral_rewards WHERE referrer_professional_id=? AND status='available' ORDER BY id");$q->execute([$referrerProfessionalId]);$rewards=$q->fetchAll();
    foreach($rewards as $reward){
        $sub=db()->prepare("SELECT asaas_subscription_id FROM professional_subscriptions WHERE professional_id=? AND asaas_subscription_id IS NOT NULL ORDER BY id DESC LIMIT 1");$sub->execute([$referrerProfessionalId]);$subscriptionId=(string)$sub->fetchColumn();
        if($subscriptionId==='')return;
        $target=(string)($reward['benefit_due_date']??'');
        if($target===''){
            $remote=asaas_request('GET','/subscriptions/'.rawurlencode($subscriptionId));
            $base=(string)($remote['nextDueDate']??date('Y-m-d'));
            $target=date('Y-m-d',strtotime($base.' +1 month'));
            db()->prepare('UPDATE referral_rewards SET benefit_due_date=? WHERE id=? AND benefit_due_date IS NULL')->execute([$target,$reward['id']]);
        }
        asaas_request('PUT','/subscriptions/'.rawurlencode($subscriptionId),['nextDueDate'=>$target]);
        db()->prepare("UPDATE referral_rewards SET status='applied',applied_at=NOW() WHERE id=? AND status='available'")->execute([$reward['id']]);
    }
}

function process_asaas_webhook(array $payload): void
{
    $eventId=(string)($payload['id']??'');$event=(string)($payload['event']??'');
    if($eventId===''||$event==='')throw new RuntimeException('Evento inválido.');
    $insert=db()->prepare('INSERT IGNORE INTO asaas_webhook_events(event_id,event_type,payload) VALUES(?,?,?)');
    $insert->execute([$eventId,$event,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    if($insert->rowCount()===0){$seen=db()->prepare('SELECT processed_at FROM asaas_webhook_events WHERE event_id=?');$seen->execute([$eventId]);if($seen->fetchColumn())return;}
    if(str_starts_with($event,'CHECKOUT_')){
        $checkout=$payload['checkout']??[];$checkoutId=(string)($checkout['id']??'');
        $q=db()->prepare('SELECT * FROM professional_subscriptions WHERE asaas_checkout_id=? ORDER BY id DESC LIMIT 1');$q->execute([$checkoutId]);$sub=$q->fetch();
        if($sub){
            if($event==='CHECKOUT_PAID'){
                db()->beginTransaction();
                db()->prepare("UPDATE professional_subscriptions SET status='active',starts_at=NOW(),ends_at=NULL,asaas_customer_id=COALESCE(?,asaas_customer_id),asaas_subscription_id=COALESCE(?,asaas_subscription_id) WHERE id=?")->execute([$checkout['customer']??null,$checkout['subscription']??null,$sub['id']]);
                db()->prepare("UPDATE professionals SET subscription_plan=?,subscription_status='active',asaas_customer_id=COALESCE(?,asaas_customer_id),access_until=NULL,trial_ends_at=NULL,cancellation_requested_at=NULL WHERE id=?")->execute([$sub['plan_code'],$checkout['customer']??null,$sub['professional_id']]);
                db()->commit();
                qualify_referral_reward((int)$sub['professional_id']);
            }elseif(in_array($event,['CHECKOUT_CANCELED','CHECKOUT_EXPIRED'],true)){
                db()->prepare("UPDATE professional_subscriptions SET status='cancelled',ends_at=NOW() WHERE id=?")->execute([$sub['id']]);
            }
        }elseif(db_table_exists('pending_professional_signups')){
            $q=db()->prepare("SELECT * FROM pending_professional_signups WHERE asaas_checkout_id=? AND status='pending' LIMIT 1");$q->execute([$checkoutId]);$pending=$q->fetch();
            if($pending&&$event==='CHECKOUT_PAID'){
                db()->beginTransaction();
                try{
                    $exists=db()->prepare('SELECT id FROM users WHERE email=? LIMIT 1');$exists->execute([$pending['email']]);
                    if($exists->fetchColumn())throw new RuntimeException('Já existe uma conta com o e-mail do cadastro pago.');
                    db()->prepare('INSERT INTO users(tenant_id,role,name,email,password_hash,phone) VALUES(?,?,?,?,?,?)')->execute([$pending['tenant_id'],'professional',$pending['name'],$pending['email'],$pending['password_hash'],$pending['phone']]);
                    $userId=(int)db()->lastInsertId();
                    $referredBy=null;
                    if(!empty($pending['referral_code'])){$ref=db()->prepare('SELECT id FROM professionals WHERE referral_code=? LIMIT 1');$ref->execute([strtoupper((string)$pending['referral_code'])]);$referredBy=(int)$ref->fetchColumn()?:null;}
                    $referralCode='KIN'.strtoupper(bin2hex(random_bytes(4)));
                    db()->prepare("INSERT INTO professionals(tenant_id,user_id,service_type,subscription_plan,subscription_status,trial_ends_at,referral_code,referred_by_professional_id,asaas_customer_id) VALUES(?,?,'personal',?,'active',NULL,?,?,?)")->execute([$pending['tenant_id'],$userId,$pending['plan_code'],$referralCode,$referredBy,$checkout['customer']??null]);
                    $professionalId=(int)db()->lastInsertId();
                    db()->prepare("INSERT INTO professional_subscriptions(tenant_id,professional_id,plan_code,price_cents,status,asaas_checkout_id,asaas_subscription_id,asaas_customer_id,external_reference,starts_at,ends_at) VALUES(?,?,?,?,'active',?,?,?,?,NOW(),NULL)")->execute([$pending['tenant_id'],$professionalId,$pending['plan_code'],$pending['price_cents'],$checkoutId,$checkout['subscription']??null,$checkout['customer']??null,$pending['external_reference']]);
                    if($referredBy)db()->prepare("INSERT IGNORE INTO referral_rewards(referrer_professional_id,referred_professional_id,status) VALUES(?,?,'pending')")->execute([$referredBy,$professionalId]);
                    db()->prepare("UPDATE pending_professional_signups SET status='paid',paid_at=NOW() WHERE id=? AND status='pending'")->execute([$pending['id']]);
                    db()->commit();
                    send_professional_welcome_email(['name'=>$pending['name'],'email'=>$pending['email']]);
                }catch(Throwable $e){
                    if(db()->inTransaction())db()->rollBack();
                    throw $e;
                }
            }elseif($pending&&in_array($event,['CHECKOUT_CANCELED','CHECKOUT_EXPIRED'],true)){
                db()->prepare("UPDATE pending_professional_signups SET status='cancelled' WHERE id=?")->execute([$pending['id']]);
            }elseif(db_table_exists('professional_addon_orders')){
                $q=db()->prepare('SELECT id FROM professional_addon_orders WHERE asaas_checkout_id=? ORDER BY id DESC LIMIT 1');$q->execute([$checkoutId]);$addonId=(int)$q->fetchColumn();
                if($addonId&&$event==='CHECKOUT_PAID')db()->prepare("UPDATE professional_addon_orders SET status='paid',paid_at=NOW() WHERE id=?")->execute([$addonId]);
                elseif($addonId&&in_array($event,['CHECKOUT_CANCELED','CHECKOUT_EXPIRED'],true))db()->prepare("UPDATE professional_addon_orders SET status='cancelled' WHERE id=?")->execute([$addonId]);
            }
        }elseif(db_table_exists('professional_addon_orders')){
            $q=db()->prepare('SELECT id FROM professional_addon_orders WHERE asaas_checkout_id=? ORDER BY id DESC LIMIT 1');$q->execute([$checkoutId]);$addonId=(int)$q->fetchColumn();
            if($addonId&&$event==='CHECKOUT_PAID')db()->prepare("UPDATE professional_addon_orders SET status='paid',paid_at=NOW() WHERE id=?")->execute([$addonId]);
            elseif($addonId&&in_array($event,['CHECKOUT_CANCELED','CHECKOUT_EXPIRED'],true))db()->prepare("UPDATE professional_addon_orders SET status='cancelled' WHERE id=?")->execute([$addonId]);
        }
    }elseif(str_starts_with($event,'SUBSCRIPTION_')){
        $remote=$payload['subscription']??[];$customer=(string)($remote['customer']??'');$subscriptionId=(string)($remote['id']??'');
        $q=db()->prepare('SELECT id FROM professionals WHERE asaas_customer_id=? LIMIT 1');$q->execute([$customer]);$professionalId=(int)$q->fetchColumn();if(!$professionalId)return;
        db()->prepare('UPDATE professional_subscriptions SET asaas_subscription_id=? WHERE professional_id=? ORDER BY id DESC LIMIT 1')->execute([$subscriptionId,$professionalId]);
        apply_referral_rewards($professionalId);
        if(in_array($event,['SUBSCRIPTION_INACTIVATED','SUBSCRIPTION_DELETED'],true))db()->prepare("UPDATE professionals SET subscription_status='cancelled' WHERE id=?")->execute([$professionalId]);
    }elseif(str_starts_with($event,'PAYMENT_')){
        $payment=$payload['payment']??[];$subscriptionId=(string)($payment['subscription']??'');if($subscriptionId==='')return;
        $q=db()->prepare('SELECT professional_id FROM professional_subscriptions WHERE asaas_subscription_id=? ORDER BY id DESC LIMIT 1');$q->execute([$subscriptionId]);$professionalId=(int)$q->fetchColumn();if(!$professionalId)return;
        if(in_array($event,['PAYMENT_CONFIRMED','PAYMENT_RECEIVED'],true)){db()->prepare("UPDATE professionals SET subscription_status='active',access_until=NULL WHERE id=? AND cancellation_requested_at IS NULL")->execute([$professionalId]);qualify_referral_reward($professionalId);}
        elseif($event==='PAYMENT_OVERDUE'){
            $dueDate=(string)($payment['dueDate']??date('Y-m-d'));
            $due=DateTimeImmutable::createFromFormat('!Y-m-d',$dueDate)?:new DateTimeImmutable('today');
            $graceUntil=$due->modify('+5 days')->setTime(23,59,59)->format('Y-m-d H:i:s');
            db()->prepare("UPDATE professionals SET subscription_status='past_due',access_until=? WHERE id=? AND cancellation_requested_at IS NULL")->execute([$graceUntil,$professionalId]);
        }
        elseif(in_array($event,['PAYMENT_REFUNDED','PAYMENT_CHARGEBACK_REQUESTED'],true))db()->prepare("UPDATE professionals SET subscription_status='past_due' WHERE id=?")->execute([$professionalId]);
    }
    db()->prepare('UPDATE asaas_webhook_events SET processed_at=NOW() WHERE event_id=?')->execute([$eventId]);
}
