<script setup lang="ts">
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { EditableCollectionItem, SelectOption } from '@/types';
import CopyFields from './Partials/CopyFields.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    item: EditableCollectionItem;
    formats: SelectOption[];
    conditions: SelectOption[];
}>();

// Empty strings are converted to null by Laravel
const form = useForm({
    format: props.item.format ?? '',
    condition: props.item.condition ?? '',
    acquired_at: props.item.acquired_at ?? '',
    notes: props.item.notes ?? '',
});

const confirmingRemoval = ref(false);
const removal = useForm({});
</script>

<template>
    <Head title="Modifier un exemplaire" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Modifier un exemplaire</h2>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <form
                    @submit.prevent="form.put(route('collection.update', item.id))"
                    class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 space-y-6"
                >
                    <!-- The album belongs to the shared catalog: read-only here -->
                    <div>
                        <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ item.title }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ item.artists.join(', ') || 'Artiste inconnu' }}
                            <span v-if="item.year"> · {{ item.year }}</span>
                        </p>
                    </div>

                    <CopyFields :form="form" :formats="formats" :conditions="conditions" />

                    <div class="flex items-center justify-end gap-4">
                        <Link
                            :href="route('collection.index')"
                            class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 underline"
                        >
                            Annuler
                        </Link>
                        <PrimaryButton :disabled="form.processing">Enregistrer</PrimaryButton>
                    </div>
                </form>

                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <DangerButton @click="confirmingRemoval = true">Retirer de ma collection</DangerButton>
                </div>
            </div>
        </div>

        <Modal :show="confirmingRemoval" @close="confirmingRemoval = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Retirer « {{ item.title }} » de votre collection ?
                </h2>

                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    L'état, la date d'acquisition et les notes de cet exemplaire seront perdus.
                </p>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="confirmingRemoval = false">Annuler</SecondaryButton>

                    <DangerButton
                        class="ms-3"
                        :class="{ 'opacity-25': removal.processing }"
                        :disabled="removal.processing"
                        @click="removal.delete(route('collection.destroy', item.id))"
                    >
                        Retirer
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
