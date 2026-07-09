<div class="form-fields">
    <div v-for="campo in fields" :key="campo.slug" class="form-fields__field"
         :style="{ gridColumn: 'span ' + (campo.coluna_span || 12) }">

        <label :for="'fd-' + getFieldKey(campo)">
            {{ campo.rotulo }}
            <span v-if="campo.obrigatorio" class="required">*</span>
        </label>

        <!-- textarea -->
        <textarea v-if="campo.tipo === 'textarea'"
                  :id="'fd-' + getFieldKey(campo)"
                  :name="getFieldKey(campo)"
                  :placeholder="campo.placeholder"
                  :required="campo.obrigatorio"
                  v-model="localValues[getFieldKey(campo)]"
                  class="input-text"
                  @input="updateValue(getFieldKey(campo), $event.target.value)">
        </textarea>

        <!-- select -->
        <select v-else-if="campo.tipo === 'select' || campo.tipo === 'gender'"
                :id="'fd-' + getFieldKey(campo)"
                :name="getFieldKey(campo)"
                :required="campo.obrigatorio"
                v-model="localValues[getFieldKey(campo)]"
                class="input-select"
                @change="updateValue(getFieldKey(campo), $event.target.value)">
            <option value="">Selecione...</option>
            <option v-for="opt in (campo.opcoes || [])" :key="opt" :value="opt">{{ opt }}</option>
        </select>

        <!-- multiselect -->
        <select v-else-if="campo.tipo === 'multiselect'"
                :id="'fd-' + getFieldKey(campo)"
                :name="getFieldKey(campo) + '[]'"
                :required="campo.obrigatorio"
                v-model="localValues[getFieldKey(campo)]"
                class="input-select"
                multiple
                @change="updateValue(getFieldKey(campo), $event.target.value)">
            <option v-for="opt in (campo.opcoes || [])" :key="opt" :value="opt">{{ opt }}</option>
        </select>

        <!-- date -->
        <input v-else-if="campo.tipo === 'date'"
               type="date"
               :id="'fd-' + getFieldKey(campo)"
               :name="getFieldKey(campo)"
               :placeholder="campo.placeholder"
               :required="campo.obrigatorio"
               v-model="localValues[getFieldKey(campo)]"
               class="input-text"
               @input="updateValue(getFieldKey(campo), $event.target.value)">

        <!-- datetime -->
        <input v-else-if="campo.tipo === 'datetime'"
               type="datetime-local"
               :id="'fd-' + getFieldKey(campo)"
               :name="getFieldKey(campo)"
               :placeholder="campo.placeholder"
               :required="campo.obrigatorio"
               v-model="localValues[getFieldKey(campo)]"
               class="input-text"
               @input="updateValue(getFieldKey(campo), $event.target.value)">

        <!-- phone / cep / cpf / cnpj -->
        <input v-else-if="campo.tipo === 'phone' || campo.tipo === 'cep' || campo.tipo === 'cpf' || campo.tipo === 'cnpj'"
               type="text"
               :id="'fd-' + getFieldKey(campo)"
               :name="getFieldKey(campo)"
               :placeholder="campo.placeholder"
               :required="campo.obrigatorio"
               v-model="localValues[getFieldKey(campo)]"
               class="input-text"
               :maxlength="campo.tipo === 'cpf' ? 14 : (campo.tipo === 'cnpj' ? 18 : (campo.tipo === 'phone' ? 15 : 9))"
               @input="updateValue(getFieldKey(campo), $event.target.value)">

        <!-- default: text / email / url / number -->
        <input v-else
               :type="campo.tipo === 'number' ? 'number' : (campo.tipo === 'email' ? 'email' : (campo.tipo === 'url' ? 'url' : 'text'))"
               :id="'fd-' + getFieldKey(campo)"
               :name="getFieldKey(campo)"
               :placeholder="campo.placeholder"
               :required="campo.obrigatorio"
               v-model="localValues[getFieldKey(campo)]"
               class="input-text"
               @input="updateValue(getFieldKey(campo), $event.target.value)">
    </div>
</div>
