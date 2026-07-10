document.addEventListener('DOMContentLoaded', function () {
    const builder = document.querySelector('[data-form-builder]');
    if (!builder) return;
    const list = builder.querySelector('[data-form-field-list]');
    const json = builder.querySelector('[data-form-fields-json]');
    const template = document.querySelector('[data-form-field-template]');
    const count = builder.querySelector('[data-form-field-count]');
    const empty = builder.querySelector('.form-add-field-empty');
    const typeMeta = {
        text: ['Короткий текст', 'mdi-form-textbox'], textarea: ['Довгий текст', 'mdi-text-long'],
        email: ['Email', 'mdi-email-outline'], tel: ['Телефон', 'mdi-phone-outline'],
        number: ['Число', 'mdi-numeric'], date: ['Дата', 'mdi-calendar-outline'],
        select: ['Випадний список', 'mdi-form-dropdown'], radio: ['Один варіант', 'mdi-radiobox-marked'],
        checkbox: ['Прапорець', 'mdi-checkbox-marked-outline'], consent: ['Згода', 'mdi-shield-check-outline']
    };
    let fields = [];
    try { fields = JSON.parse(json.value || '[]'); } catch (e) { fields = []; }

    function slug(value, index) {
        const normalized = String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/gi, '_').replace(/^_+|_+$/g, '');
        return normalized || 'field_' + (index + 1);
    }
    function optionsText(options) { return (options || []).map(function (o) { return typeof o === 'string' ? o : (o.label || o.value || ''); }).filter(Boolean).join('\n'); }
    function read() {
        fields = Array.from(list.querySelectorAll('[data-form-field]')).map(function (card, index) {
            const label = card.querySelector('[data-field-label]').value.trim();
            const options = card.querySelector('[data-field-options]').value.split('\n').map(function (v) { return v.trim(); }).filter(Boolean).map(function (v, optionIndex) { const value=slug(v, optionIndex); return {value: value.indexOf('field_')===0?'option_'+(optionIndex+1):value, label: v}; });
            return {id: card.dataset.fieldId || slug(label, index), type: card.querySelector('[data-field-type]').value, label: label, required: card.querySelector('[data-field-required]').checked, options: options};
        });
        json.value = JSON.stringify(fields);
        count.textContent = fields.length;
        empty.hidden = fields.length > 0;
        builder.querySelector('[data-form-save-hint]').textContent = 'Є незбережені зміни';
    }
    function updateCard(card) {
        const label = card.querySelector('[data-field-label]').value.trim() || 'Нове поле';
        const type = card.querySelector('[data-field-type]').value;
        card.querySelector('[data-field-summary]').textContent = label;
        card.querySelector('[data-field-type-label]').textContent = (typeMeta[type] || [type])[0];
        card.querySelector('.form-field-type-icon').className = 'form-field-type-icon mdi ' + (typeMeta[type] || ['', 'mdi-form-textbox'])[1];
        card.querySelector('[data-field-options-wrap]').hidden = !['select', 'radio'].includes(type);
    }
    function make(field, index) {
        const card = template.content.firstElementChild.cloneNode(true);
        card.dataset.fieldId = field.id || slug(field.label, index);
        card.querySelector('[data-field-label]').value = field.label || '';
        card.querySelector('[data-field-type]').value = field.type || 'text';
        card.querySelector('[data-field-required]').checked = Boolean(field.required);
        card.querySelector('[data-field-options]').value = optionsText(field.options);
        updateCard(card); return card;
    }
    function render() { list.innerHTML = ''; fields.forEach(function (field, index) { list.appendChild(make(field, index)); }); count.textContent = fields.length; empty.hidden = fields.length > 0; }
    function add() { const card = make({id:'field_'+Date.now(),type:'text',label:'',required:false,options:[]}, fields.length); list.appendChild(card); read(); card.querySelector('[data-field-label]').focus(); card.scrollIntoView({behavior:'smooth', block:'center'}); }
    builder.querySelectorAll('[data-form-add-field]').forEach(function (button) { button.addEventListener('click', add); });
    list.addEventListener('input', function (event) { const card=event.target.closest('[data-form-field]'); if(card) updateCard(card); read(); });
    list.addEventListener('change', function (event) { const card=event.target.closest('[data-form-field]'); if(card) updateCard(card); read(); });
    list.addEventListener('click', function (event) {
        const card=event.target.closest('[data-form-field]'); if(!card) return;
        if(event.target.closest('[data-field-remove]')) { if(confirm('Видалити це поле?')) { card.remove(); read(); } }
        if(event.target.closest('[data-field-collapse]')) { card.classList.toggle('is-collapsed'); event.target.closest('button').querySelector('.mdi').className='mdi '+(card.classList.contains('is-collapsed')?'mdi-chevron-down':'mdi-chevron-up'); }
    });
    let dragging=null;
    list.addEventListener('dragstart', function(e){ dragging=e.target.closest('[data-form-field]'); if(dragging) dragging.classList.add('is-dragging'); });
    list.addEventListener('dragend', function(){ if(dragging) dragging.classList.remove('is-dragging'); dragging=null; read(); });
    list.addEventListener('dragover', function(e){ e.preventDefault(); const target=e.target.closest('[data-form-field]'); if(target&&dragging&&target!==dragging){ const box=target.getBoundingClientRect(); list.insertBefore(dragging, e.clientY<box.top+box.height/2?target:target.nextSibling); } });
    builder.addEventListener('submit', function (event) { read(); if (!fields.length) { event.preventDefault(); alert('Додайте хоча б одне поле.'); } });
    builder.addEventListener('input', function(){ builder.querySelector('[data-form-save-hint]').textContent='Є незбережені зміни'; });
    render();
});
