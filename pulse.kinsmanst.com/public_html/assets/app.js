document.addEventListener('DOMContentLoaded',()=>{
  const editForm=[...document.querySelectorAll('form')].find(form=>
    form.querySelector('[name="action"][value="save_workout_item"]') &&
    Number(form.querySelector('[name="item_id"]')?.value)>0);
  if(editForm){
    const itemId=editForm.querySelector('[name="item_id"]').value;
    const editLink=[...document.querySelectorAll('.workout-list .mini-link')].find(link=>
      new URL(link.href,location.href).searchParams.get('edit_item')===itemId);
    const card=editLink?.closest('article');
    const editor=editForm.closest('section.panel');
    if(card&&editor&&!card.contains(editor)){
      editor.classList.remove('sticky');
      editor.classList.add('workout-inline-editor');
      card.append(editor);
      card.id='editing-exercise-'+itemId;
      card.scrollIntoView({block:'start'});
    }
  }
  const exportDialog=document.querySelector('.workout-export-dialog');
  if(exportDialog){
    const form=exportDialog.querySelector('form');
    const options=[...form.querySelectorAll('input[name="ids[]"]')];
    const all=form.querySelector('[data-export-all]');
    const submit=form.querySelector('[data-export-submit]');
    const update=()=>{
      const count=options.filter(option=>option.checked).length;
      all.checked=count===options.length;
      all.indeterminate=count>0&&count<options.length;
      submit.disabled=count===0;
      form.querySelector('.export-count').textContent=count ? count+' treino'+(count===1?' selecionado':'s selecionados') : 'Nenhum treino selecionado';
    };
    document.querySelector('[data-open-workout-export]')?.addEventListener('click',()=>exportDialog.showModal());
    form.querySelector('[data-close-workout-export]').addEventListener('click',()=>exportDialog.close());
    all.addEventListener('change',()=>{options.forEach(option=>option.checked=all.checked);update();});
    options.forEach(option=>option.addEventListener('change',update));
    form.addEventListener('submit',event=>{if(!options.some(option=>option.checked))event.preventDefault();});
    update();
  }
  const button = document.querySelector('.menu-toggle');
  const sidebar = document.querySelector('.sidebar');

  button?.addEventListener('click', event => {
    event.stopPropagation();
    sidebar?.classList.toggle('open');
    button.setAttribute('aria-expanded', String(sidebar?.classList.contains('open')));
  });

  document.addEventListener('click', event => {
    const clickedOutsideMenu = !sidebar?.contains(event.target) && !button?.contains(event.target);
    if (innerWidth <= 900 && sidebar?.classList.contains('open') && clickedOutsideMenu) {
      sidebar.classList.remove('open');
      button?.setAttribute('aria-expanded', 'false');
    }
  });
  const muscleZones=document.querySelectorAll('.muscle-zone');
  if(muscleZones.length){
    const tip=document.createElement('div');
    tip.className='muscle-tip';
    tip.hidden=true;
    document.body.append(tip);
    muscleZones.forEach(zone=>{
      const label=zone.querySelector('title');
      if(!label)return;
      zone.addEventListener('mouseenter',()=>{
        tip.textContent=label.textContent;
        tip.hidden=false;
        const rect=zone.getBoundingClientRect();
        tip.style.left=`${rect.left+rect.width/2}px`;
        tip.style.top=`${Math.max(6,rect.top-36)}px`;
      });
      zone.addEventListener('mouseleave',()=>{tip.hidden=true;});
      zone.addEventListener('click',()=>{tip.hidden=true;});
    });
  }
  const cookieBanner=document.getElementById('cookieBanner');
  const cookieChoice=localStorage.getItem('kinsman_cookie_consent');
  if(cookieBanner&&!cookieChoice)cookieBanner.hidden=false;
  document.querySelectorAll('[data-cookie-choice]').forEach(choice=>choice.addEventListener('click',()=>{
    localStorage.setItem('kinsman_cookie_consent',choice.dataset.cookieChoice);
    localStorage.setItem('kinsman_cookie_consent_at',new Date().toISOString());
    if(cookieBanner)cookieBanner.hidden=true;
  }));
  document.getElementById('resetCookieChoice')?.addEventListener('click',()=>{
    localStorage.removeItem('kinsman_cookie_consent');
    localStorage.removeItem('kinsman_cookie_consent_at');
    if(cookieBanner)cookieBanner.hidden=false;
  });
  const colorInput=document.querySelector('input[name="primary_color"]');
  colorInput?.addEventListener('input',()=>{
    document.documentElement.style.setProperty('--brand',colorInput.value);
    document.documentElement.style.setProperty('--brand-dark',`color-mix(in srgb,${colorInput.value} 58%,#061f19)`);
    document.documentElement.style.setProperty('--brand-soft',`color-mix(in srgb,${colorInput.value} 11%,#fff)`);
  });
  const carousel=document.querySelector('[data-demo-carousel]');
  if(carousel){
    const slides=[...carousel.querySelectorAll('[data-demo-slide]')];
    const dots=[...carousel.querySelectorAll('[data-demo-dot]')];
    const titles=['Painel do profissional','Gestão dos alunos','Página do aluno','Dieta, treino e evolução'];
    const counter=carousel.querySelector('[data-demo-counter]');
    const title=carousel.querySelector('[data-demo-title]');
    let current=0;
    let timer;
    const show=index=>{
      current=(index+slides.length)%slides.length;
      slides.forEach((slide,i)=>{
        const active=i===current;
        slide.classList.toggle('is-active',active);
        slide.setAttribute('aria-hidden',String(!active));
      });
      dots.forEach((dot,i)=>{
        const active=i===current;
        dot.classList.toggle('is-active',active);
        dot.setAttribute('aria-selected',String(active));
      });
      if(counter)counter.textContent=`${current+1} de ${slides.length}`;
      if(title)title.textContent=titles[current];
    };
    const autoplay=()=>{
      clearInterval(timer);
      if(!matchMedia('(prefers-reduced-motion: reduce)').matches)timer=setInterval(()=>show(current+1),7000);
    };
    carousel.querySelector('[data-demo-prev]')?.addEventListener('click',()=>{show(current-1);autoplay();});
    carousel.querySelector('[data-demo-next]')?.addEventListener('click',()=>{show(current+1);autoplay();});
    dots.forEach((dot,i)=>dot.addEventListener('click',()=>{show(i);autoplay();}));
    carousel.addEventListener('mouseenter',()=>clearInterval(timer));
    carousel.addEventListener('mouseleave',autoplay);
    carousel.addEventListener('focusin',()=>clearInterval(timer));
    carousel.addEventListener('focusout',autoplay);
    show(0);
    autoplay();
  }
  const workoutCards=[...document.querySelectorAll('.exercise-check-card')];
  if(workoutCards.length){
    workoutCards.forEach(card=>{
      const times=card.querySelector('.exercise-times');
      if(!times||card.querySelector('.performed-fields'))return;
      const fields=document.createElement('div');
      fields.className='performed-fields';
      fields.innerHTML='<label>Carga utilizada<input name="performed_load" maxlength="40" placeholder="Ex.: 12 kg"></label><label>Repetições feitas<input name="performed_repetitions" maxlength="40" placeholder="Ex.: 3 × 10"></label>';
      times.before(fields);
    });
    const originalFetch=window.fetch.bind(window);
    window.fetch=(input,options={})=>{
      const body=options.body;
      if(body instanceof URLSearchParams&&body.get('action')==='update_workout_exercise'){
        const card=document.querySelector(`.exercise-check-card[data-item="${CSS.escape(body.get('workout_item_id')||'')}"]`);
        if(card){body.set('performed_load',card.querySelector('[name="performed_load"]')?.value||'');body.set('performed_repetitions',card.querySelector('[name="performed_repetitions"]')?.value||'');}
      }
      return originalFetch(input,options);
    };
  }
});
.password-checklist {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px 14px;
  margin: -2px 0 4px;
  padding: 14px 16px;
  list-style: none;
  border: 1px solid #d9e5de;
  border-radius: 10px;
  background: #f7faf8;
}

.password-checklist li {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #718278;
  font-size: 12px;
  line-height: 1.35;
}

.password-checklist li::before {
  content: "○";
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 17px;
  height: 17px;
  flex: 0 0 17px;
  border-radius: 50%;
  color: #819188;
  border: 1px solid #bccbc2;
  font-size: 11px;
}

.password-checklist li.is-valid {
  color: #176b50;
}

.password-checklist li.is-valid::before {
  content: "✓";
  color: #ffffff;
  border-color: #176b50;
  background: #176b50;
  font-weight: 800;
}

#register-submit:disabled {
  cursor: not-allowed;
  opacity: .55;
}

@media (max-width: 600px) {
  .password-checklist {
    grid-template-columns: 1fr;
  }
}
