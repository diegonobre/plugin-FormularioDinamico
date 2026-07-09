<?php
/**
 * Inicialização do componente form-builder
 *
 * Prepara dados para o JS, incluindo tipos de campo disponíveis
 */

use MapasCulturais\i;

$fieldTypes = [
    ['type' => 'text',       'label' => i::__('Texto'),        'icon' => 'cil:bold'],
    ['type' => 'textarea',   'label' => i::__('Texto Longo'),  'icon' => 'cil:paragraph'],
    ['type' => 'number',     'label' => i::__('Número'),       'icon' => 'cil:calculator'],
    ['type' => 'email',      'label' => i::__('Email'),        'icon' => 'cil:envelope-closed'],
    ['type' => 'url',        'label' => i::__('URL'),          'icon' => 'cil:link'],
    ['type' => 'date',       'label' => i::__('Data'),         'icon' => 'cil:calendar'],
    ['type' => 'datetime',   'label' => i::__('Data e Hora'),  'icon' => 'cil:clock'],
    ['type' => 'phone',      'label' => i::__('Telefone'),     'icon' => 'cil:phone'],
    ['type' => 'cep',        'label' => i::__('CEP'),          'icon' => 'cil:location-pin'],
    ['type' => 'cpf',        'label' => i::__('CPF'),          'icon' => 'cil:user'],
    ['type' => 'cnpj',       'label' => i::__('CNPJ'),         'icon' => 'cil:briefcase'],
    ['type' => 'select',     'label' => i::__('Seleção'),      'icon' => 'cil:chevron-bottom'],
    ['type' => 'multiselect','label' => i::__('Multi Seleção'),'icon' => 'cil:list'],
    ['type' => 'gender',     'label' => i::__('Gênero'),       'icon' => 'cil:user-follow'],
];

$this->jsObject['formBuilderFieldTypes'] = $fieldTypes;
