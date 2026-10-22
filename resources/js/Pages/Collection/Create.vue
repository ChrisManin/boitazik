<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { SelectOption } from '@/types';
import CopyFields from './Partials/CopyFields.vue';
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

                    <CopyFields :form="form" :formats="formats" :conditions="conditions">
                        <div>
                            <InputLabel for="year" value="Année de sortie" />
                            <TextInput id="year" type="number" class="mt-1 block w-full" v-model="form.year" />
                            <InputError class="mt-2" :message="form.errors.year" />
                        </div>
                    </CopyFields>

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
