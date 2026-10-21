<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { SelectOption } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    formats: SelectOption[];
    conditions: SelectOption[];
}>();

// Empty strings are converted to null by Laravel
const form = useForm({
    artist: '',
    title: '',
    year: '',
    format: '',
    condition: '',
    acquired_at: '',
    notes: '',
});

const fieldClass =
    'mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm';
</script>

<template>
    <Head title="Ajouter un album" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Ajouter un album</h2>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
                <form
                    @submit.prevent="form.post(route('collection.store'))"
                    class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 space-y-6"
                >
                    <div>
                        <InputLabel for="artist" value="Artiste *" />
                        <TextInput id="artist" type="text" class="mt-1 block w-full" v-model="form.artist" required autofocus />
                        <InputError class="mt-2" :message="form.errors.artist" />
                    </div>

                    <div>
                        <InputLabel for="title" value="Titre *" />
                        <TextInput id="title" type="text" class="mt-1 block w-full" v-model="form.title" required />
                        <InputError class="mt-2" :message="form.errors.title" />
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <InputLabel for="year" value="Année de sortie" />
                            <TextInput id="year" type="number" class="mt-1 block w-full" v-model="form.year" />
                            <InputError class="mt-2" :message="form.errors.year" />
                        </div>

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

                    <div class="flex items-center justify-end gap-4">
                        <Link
                            :href="route('collection.index')"
                            class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 underline"
                        >
                            Annuler
                        </Link>
                        <PrimaryButton :disabled="form.processing">Ajouter</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
