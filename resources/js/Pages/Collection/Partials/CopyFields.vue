<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { SelectOption } from '@/types';

type CopyField = 'format' | 'condition' | 'acquired_at' | 'notes';

// Fields the user owns on a copy, shared by the create and edit forms
defineProps<{
    form: Record<CopyField, string> & { errors: Partial<Record<CopyField, string>> };
    formats: SelectOption[];
    conditions: SelectOption[];
}>();

const fieldClass =
    'mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm';
</script>

<template>
    <!-- The default slot adds cells at the start of the grid -->
    <div class="grid gap-6 sm:grid-cols-2">
        <slot />

        <div>
            <InputLabel for="format" value="Format" />
            <select id="format" :class="fieldClass" v-model="form.format">
                <option value="">Inconnu</option>
                <option v-for="option in formats" :key="option.value" :value="option.value">
                    {{ option.label }}
                </option>
            </select>
            <InputError class="mt-2" :message="form.errors.format" />
        </div>

        <div>
            <InputLabel for="condition" value="État" />
            <select id="condition" :class="fieldClass" v-model="form.condition">
                <option value="">Non renseigné</option>
                <option v-for="option in conditions" :key="option.value" :value="option.value">
                    {{ option.label }}
                </option>
            </select>
            <InputError class="mt-2" :message="form.errors.condition" />
        </div>

        <div>
            <InputLabel for="acquired_at" value="Date d'acquisition" />
            <TextInput id="acquired_at" type="date" class="mt-1 block w-full" v-model="form.acquired_at" />
            <InputError class="mt-2" :message="form.errors.acquired_at" />
        </div>
    </div>

    <div>
        <InputLabel for="notes" value="Notes" />
        <textarea id="notes" rows="3" :class="fieldClass" v-model="form.notes"></textarea>
        <InputError class="mt-2" :message="form.errors.notes" />
    </div>
</template>
