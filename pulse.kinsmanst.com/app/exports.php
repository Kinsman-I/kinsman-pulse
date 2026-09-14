<?php
declare(strict_types=1);

function render_workout_export_picker(array $u): void
{
    $q=db()->prepare("SELECT d.id,d.name,d.objective FROM workout_sheets d JOIN students s ON s.id=d.student_id WHERE s.user_id=? AND s.tenant_id=? AND d.status='published' ORDER BY d.name,d.id");
    $q->execute([$u['id'],$u['tenant_id']]);
    $sheets=$q->fetchAll();
    if(!$sheets)return;
    ?>
    <div class="student-export"><button class="primary button" type="button" data-open-workout-export><span class="pdf-icon" aria-hidden="true">PDF</span>Baixar treinos</button></div>
    <dialog class="workout-export-dialog" aria-labelledby="workout-export-title">
      <form method="get" action="index.php" target="_blank" data-workout-export-form>
        <input type="hidden" name="page" value="export-workout">
        <input type="hidden" name="print" value="1">
        <header><h2 id="workout-export-title">Quais treinos deseja baixar?</h2><p>Escolha um, vários ou todos. Os selecionados serão reunidos em um único PDF.</p></header>
        <label class="export-all"><input type="checkbox" data-export-all> Selecionar todos</label>
        <div class="export-options">
        <?php foreach($sheets as $sheet): ?>
          <label class="export-option"><input type="checkbox" name="ids[]" value="<?=(int)$sheet['id']?>"><span><strong><?=e($sheet['name'])?></strong><?php if(!empty($sheet['objective'])):?><small><?=e($sheet['objective'])?></small><?php endif;?></span></label>
        <?php endforeach; ?>
        </div>
        <p class="export-count" role="status" aria-live="polite">Nenhum treino selecionado</p>
        <footer><button type="button" class="secondary" data-close-workout-export>Cancelar</button><button type="submit" class="primary" disabled data-export-submit>Gerar PDF</button></footer>
      </form>
    </dialog>
    <?php
}

function export_professional_document(array $u,string $type,int $id): void
{
    require_role(['professional','student']);
    if(!in_array($type,['workout','food'],true)||$id<0){http_response_code(404);exit('Documento não encontrado.');}
    if($u['role']==='student' && (($type==='workout' && ($u['access_service_type']??'complete')==='nutrition') || ($type==='food' && ($u['access_service_type']??'complete')==='personal'))){
        http_response_code(403);exit('Esse serviço não faz parte do seu acompanhamento.');
    }
    $selection = $type==='workout' && array_key_exists('ids',$_GET);
    $ids = $selection ? $_GET['ids'] : [$id];
    if(!is_array($ids) || !$ids || count($ids)>100){http_response_code(400);exit('Selecione de 1 a 100 treinos.');}
    foreach($ids as $value){
        if(!is_scalar($value) || !preg_match('/^[0-9]+$/',(string)$value) || ($selection && (int)$value<1)){http_response_code(400);exit('Seleção de treinos inválida.');}
    }
    $ids=array_values(array_unique(array_map('intval',$ids)));
    $documents=[];
    foreach($ids as $id){
    $table=$type==='workout'?'workout_sheets':'food_plans';
    if($u['role']==='student'&&$id===0){
        $latest=db()->prepare("SELECT d.id FROM {$table} d JOIN students s ON s.id=d.student_id WHERE s.user_id=? AND d.status='published' ORDER BY d.published_at DESC,d.id DESC LIMIT 1");
        $latest->execute([$u['id']]);$id=(int)$latest->fetchColumn();
    }
    if($id<1){http_response_code(404);exit('Nenhum documento publicado para emissão.');}
    $stmt=db()->prepare("SELECT d.*,s.birth_date,s.height_cm,s.current_weight_kg,s.target_weight_kg,s.objective student_objective,
      s.user_id student_user_id,p.user_id professional_user_id,
      su.name student_name,su.email student_email,su.phone student_phone,
      p.registration_number,p.specialty,p.brand_name,p.logo_path,p.primary_color,p.whatsapp,p.instagram,
      pu.name professional_name,pu.email professional_email,pu.phone professional_phone
      FROM {$table} d
      JOIN students s ON s.id=d.student_id
      JOIN users su ON su.id=s.user_id
      JOIN professionals p ON p.id=d.professional_id
      JOIN users pu ON pu.id=p.user_id
      WHERE d.id=? AND s.tenant_id=?");
    $stmt->execute([$id,$u['tenant_id']]);$doc=$stmt->fetch();
    if(!$doc){http_response_code(404);exit('Documento não encontrado ou sem permissão.');}
    $allowed=$u['role']==='professional'
      ?(int)$doc['professional_user_id']===(int)$u['id']
      :((int)$doc['student_user_id']===(int)$u['id']&&$doc['status']==='published');
    if(!$allowed){http_response_code(403);exit('Você não tem permissão para emitir este documento.');}
    $items=db()->prepare($type==='workout'
      ?'SELECT wi.*,e.name,e.muscle_group,e.instructions FROM workout_items wi JOIN exercise_catalog e ON e.id=wi.exercise_id WHERE wi.workout_sheet_id=? ORDER BY wi.sort_order,wi.id'
      :'SELECT * FROM meals WHERE food_plan_id=? ORDER BY sort_order,meal_time,id');
    $items->execute([$id]);$rows=$items->fetchAll();
    $doc['_rows']=$rows;
    if($documents && (int)$documents[0]['student_user_id']!==(int)$doc['student_user_id']){
        http_response_code(400);exit('Selecione treinos de um único aluno.');
    }
    $documents[]=$doc;
    }
    if($type==='workout')usort($documents,fn($a,$b)=>strnatcasecmp($a['name'],$b['name']) ?: ($a['id']<=>$b['id']));
    $doc=$documents[0];
    $color=(string)($doc['primary_color']?:'#16765f');if(!preg_match('/^#[0-9a-fA-F]{6}$/',$color))$color='#16765f';
    $logo=(string)($doc['logo_path']?:'assets/images/logo-kinsman.png');$brand=(string)($doc['brand_name']?:$doc['professional_name']);
    $age=$doc['birth_date']?(new DateTime($doc['birth_date']))->diff(new DateTime('today'))->y:null;$title=$type==='workout'?'Ficha de treino':'Plano alimentar';
    ?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title.' - '.$doc['student_name'])?></title>
<style>
@page{size:A4;margin:14mm}*{box-sizing:border-box}body{margin:0;color:#123c32;font-family:Arial,Helvetica,sans-serif;background:#eef5f2}.print-tools{position:sticky;top:0;display:flex;justify-content:center;gap:10px;padding:12px;background:#073d33;z-index:5}.print-tools button,.print-tools a{border:0;border-radius:9px;padding:10px 17px;font-weight:700;text-decoration:none;cursor:pointer}.print-tools button{background:<?=$color?>;color:#fff}.print-tools a{background:#fff;color:#073d33}.sheet{width:210mm;min-height:297mm;margin:18px auto;padding:16mm;background:#fff;box-shadow:0 12px 40px rgba(6,45,35,.15)}.doc-head{display:flex;align-items:center;gap:15px;padding-bottom:16px;border-bottom:3px solid <?=$color?>}.doc-head img{width:64px;height:64px;object-fit:contain;border-radius:13px;border:1px solid #d9e6e1;padding:5px}.doc-head div{flex:1}.doc-head h1{font-size:24px;margin:0 0 4px}.doc-head p{margin:0;color:#61766f}.doc-type{text-align:right}.doc-type b{display:block;color:<?=$color?>;font-size:17px}.doc-type small{color:#71847e}.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:17px 0}.info-card{border:1px solid #d9e6e1;border-radius:12px;padding:13px}.info-card h2{font-size:12px;letter-spacing:.1em;color:<?=$color?>;margin:0 0 9px}.info-card p{margin:4px 0;font-size:12px}.summary{background:#f1f7f4;border-left:4px solid <?=$color?>;padding:12px 14px;margin:14px 0;border-radius:0 10px 10px 0}.summary b{display:block;margin-bottom:4px}.summary p{margin:0;font-size:12px;line-height:1.5}.item{break-inside:avoid;border:1px solid #d9e6e1;border-radius:12px;margin:10px 0;overflow:hidden}.item-head{display:flex;align-items:center;gap:10px;padding:10px 12px;background:#f5f9f7}.number{display:grid;place-items:center;width:30px;height:30px;border-radius:8px;background:<?=$color?>;color:#fff;font-weight:800}.item-head div{flex:1}.item h3{font-size:14px;margin:0}.item small{color:#71847e}.values{display:grid;grid-template-columns:repeat(4,1fr);border-top:1px solid #e0ebe7}.values span{padding:9px 11px;border-right:1px solid #e0ebe7;font-size:11px}.values span:last-child{border:0}.values b{display:block;font-size:12px;margin-top:3px}.item-copy,.meal-copy{padding:10px 12px;font-size:11px;line-height:1.5}.meal-time{font-size:13px;color:<?=$color?>;font-weight:800}.meal-copy strong{display:block;margin-top:7px;color:<?=$color?>}.doc-footer{margin-top:20px;padding-top:10px;border-top:1px solid #d9e6e1;display:flex;justify-content:space-between;color:#71847e;font-size:10px}@media print{body{background:#fff}.print-tools{display:none}.sheet{width:auto;min-height:auto;margin:0;padding:0;box-shadow:none}}
.sheet{box-shadow:none;border:1px solid #d9e6e1;max-width:calc(100% - 24px);height:auto}.sheet+.sheet{break-before:page}.doc-head h1{font-size:21px;overflow-wrap:anywhere}.doc-head{flex-wrap:wrap}.info-card{min-width:0;overflow-wrap:anywhere}.print-tools{flex-wrap:wrap}.item-copy:empty{display:none}
@media screen and (max-width:600px){.sheet{padding:20px 16px;min-height:0}.info-grid{grid-template-columns:1fr}.values{grid-template-columns:repeat(2,1fr)}.doc-type{text-align:left}.doc-footer{gap:12px;flex-wrap:wrap}}
@media print{.sheet{max-width:none;border:0}.doc-head,.summary{break-inside:avoid}.doc-head{break-after:avoid}}
</style></head><body><div class="print-tools"><button onclick="window.print()">Salvar como PDF / Imprimir</button><a href="javascript:window.close()">Fechar</a></div><?php foreach($documents as $doc): $rows=$doc['_rows']; ?><main class="sheet"><header class="doc-head"><img src="<?=e($logo)?>" alt="Logo"><div><h1><?=e($brand)?></h1><p><?=e($doc['specialty']?:($type==='workout'?'Personal trainer':'Nutricionista'))?></p></div><div class="doc-type"><b><?=e($title)?></b><small>Versão <?=e((string)$doc['version'])?> · <?=date('d/m/Y')?></small></div></header>
<section class="info-grid"><div class="info-card"><h2>PROFISSIONAL RESPONSÁVEL</h2><p><b><?=e($doc['professional_name'])?></b></p><p>Registro: <?=e($doc['registration_number']?:'Não informado')?></p><p><?=e($doc['professional_email'])?><?=!empty($doc['professional_phone'])?' · '.e($doc['professional_phone']):''?></p><?php if($doc['whatsapp']):?><p>WhatsApp: <?=e($doc['whatsapp'])?></p><?php endif;?></div><div class="info-card"><h2>DADOS DO ALUNO</h2><p><b><?=e($doc['student_name'])?></b><?=$age!==null?' · '.$age.' anos':''?></p><p>Altura: <?=$doc['height_cm']!==null?number_format((float)$doc['height_cm']/100,2,',','.').' m':'Não informada'?> · Peso: <?=$doc['current_weight_kg']!==null?number_format((float)$doc['current_weight_kg'],1,',','.').' kg':'Não informado'?></p><p>Meta: <?=$doc['target_weight_kg']!==null?number_format((float)$doc['target_weight_kg'],1,',','.').' kg':'Não informada'?></p><p><?=e($doc['student_email'])?><?=!empty($doc['student_phone'])?' · '.e($doc['student_phone']):''?></p></div></section>
<section class="summary"><b><?=e((string)($doc['name']??$doc['title']))?></b><p><?=nl2br(e($doc['objective']?:($doc['student_objective']?:'Objetivo não informado')))?></p><?php if($type==='food'&&!empty($doc['general_guidance'])):?><p><b>Orientações gerais:</b> <?=nl2br(e($doc['general_guidance']))?></p><?php endif;?></section>
<?php if(!$rows):?><p>Nenhum item cadastrado neste documento.</p><?php endif;?>
<?php foreach($rows as $index=>$row):?><?php if($type==='workout'):?><article class="item"><div class="item-head"><span class="number"><?=str_pad((string)($index+1),2,'0',STR_PAD_LEFT)?></span><div><small><?=e($row['muscle_group']?:'Exercício')?></small><h3><?=e($row['name'])?></h3></div></div><div class="values"><span>Séries<b><?=e((string)($row['sets_count']?:'-'))?></b></span><span>Repetições<b><?=e($row['repetitions']?:'-')?></b></span><span>Carga<b><?=e($row['suggested_load']?:'Livre')?></b></span><span>Descanso<b><?=$row['rest_seconds']!==null?e((string)$row['rest_seconds']).' s':'-'?></b></span></div><div class="item-copy"><?php if($row['instructions']):?><b>Execução:</b> <?=nl2br(e($row['instructions']))?><?php endif;?><?php if($row['notes']):?><br><b>Observações:</b> <?=nl2br(e($row['notes']))?><?php endif;?></div></article><?php else:?><article class="item"><div class="item-head"><span class="number"><?=str_pad((string)($index+1),2,'0',STR_PAD_LEFT)?></span><div><span class="meal-time"><?=e(substr((string)$row['meal_time'],0,5))?></span><h3><?=e($row['name'])?></h3></div></div><div class="meal-copy"><?=nl2br(e($row['foods']))?><?php if($row['substitutions']):?><strong>Substituições</strong><?=nl2br(e($row['substitutions']))?><?php endif;?></div></article><?php endif;?><?php endforeach;?>
<footer class="doc-footer"><span><?=e($brand)?> · <?=e($doc['professional_email'])?></span><span>Emitido pelo Kinsman Pulse em <?=date('d/m/Y H:i')?></span></footer></main><?php endforeach; ?><?php if(($_GET['print']??'')==='1'):?><script>window.addEventListener('load',()=>setTimeout(()=>window.print(),500))</script><?php endif;?></body></html><?php
}
