<script setup lang="ts">
import FormField from '@/Components/app/FormField.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import { Checkbox } from '@/Components/ui/checkbox'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import type { SchemeFormOptions, SchemeIntegerRuleKey, SchemeRuleFields } from '@/types/scheme'

// W107 — aturan bertipe dari SchemeRuleCatalog, bukan editor kunci–nilai bebas.
const props = defineProps<{
    options: SchemeFormOptions
    errors: Partial<Record<string, string>>
}>()
const rules = defineModel<SchemeRuleFields>({ required: true })

const integerRules = props.options.integerRules.flatMap((option) =>
    isIntegerKey(option.value) ? [{ key: option.value, label: option.label }] : []
)

function isIntegerKey(key: string): key is SchemeIntegerRuleKey {
    return key in rules.value && key !== 'allowed_output_types'
}

const setInteger = (key: SchemeIntegerRuleKey, value: string | number) =>
    (rules.value = { ...rules.value, [key]: String(value) })

function toggleOutputType(code: string, checked: boolean | 'indeterminate') {
    const others = rules.value.allowed_output_types.filter((value) => value !== code)
    rules.value = {
        ...rules.value,
        allowed_output_types: checked === true ? [...others, code] : others,
    }
}
</script>

<template>
    <SectionCard title="Aturan Tambahan">
        <div class="grid gap-4 sm:grid-cols-3">
            <FormField
                v-for="rule in integerRules"
                :id="`scheme-rule-${rule.key}`"
                :key="rule.key"
                v-slot="{ describedBy }"
                :label="rule.label"
                :error="errors[`rules.${rule.key}`]"
            >
                <Input
                    :id="`scheme-rule-${rule.key}`"
                    type="number"
                    min="1"
                    :model-value="rules[rule.key]"
                    :aria-describedby="describedBy"
                    @update:model-value="(value) => setInteger(rule.key, value)"
                />
            </FormField>
        </div>
        <fieldset class="space-y-2">
            <legend class="text-sm font-medium">Tipe Luaran yang Diizinkan</legend>
            <p class="text-xs text-muted-foreground">
                Kosongkan untuk mengizinkan semua tipe luaran aktif.
            </p>
            <div class="flex flex-wrap gap-x-6 gap-y-2">
                <div
                    v-for="option in options.outputTypes"
                    :key="option.value"
                    class="flex items-center gap-2"
                >
                    <Checkbox
                        :id="`scheme-output-${option.value}`"
                        :model-value="rules.allowed_output_types.includes(option.value)"
                        @update:model-value="(checked) => toggleOutputType(option.value, checked)"
                    />
                    <Label :for="`scheme-output-${option.value}`" class="font-normal">{{
                        option.label
                    }}</Label>
                </div>
            </div>
            <p v-if="errors['rules.allowed_output_types']" class="text-xs text-destructive">
                {{ errors['rules.allowed_output_types'] }}
            </p>
        </fieldset>
        <p class="text-xs text-muted-foreground">
            Aturan tambahan langsung berlaku pada skema ini.
        </p>
    </SectionCard>
</template>
