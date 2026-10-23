<script setup lang="ts">
import InputLabel from '@/Components/InputLabel.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { MusicBrainzEdition } from '@/types';
import EditionSummary from './EditionSummary.vue';
import axios from 'axios';
import { computed, onBeforeUnmount, ref } from 'vue';

const emit = defineEmits<{
    select: [edition: MusicBrainzEdition];
    // No edition for this barcode: the title search takes over
    notFound: [];
    // Server message when MusicBrainz is down, null once a search works again
    error: [message: string | null];
}>();

const code = ref('');
const editions = ref<MusicBrainzEdition[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const invalid = ref<string | null>(null);
// Tells "no search yet" apart from "a search found nothing"
const searched = ref(false);

let controller: AbortController | undefined;

function setError(message: string | null) {
    error.value = message;
    emit('error', message);
}

async function search() {
    const query = code.value.trim();
    if (query === '') {
        return;
    }

    controller?.abort();
    controller = new AbortController();
    const current = controller;
    loading.value = true;
    invalid.value = null;
    editions.value = [];
    searched.value = false;

    try {
        const response = await axios.get<{ data: MusicBrainzEdition[] }>(route('collection.barcode'), {
            params: { code: query },
            signal: current.signal,
        });
        setError(null);
        searched.value = true;

        // A single edition needs no choice
        if (response.data.data.length === 1) {
            emit('select', response.data.data[0]);
        } else {
            editions.value = response.data.data;
            if (editions.value.length === 0) {
                emit('notFound');
            }
        }
    } catch (e) {
        if (axios.isCancel(e)) {
            return;
        }
        if (axios.isAxiosError(e) && e.response?.status === 503) {
            setError(e.response.data?.message ?? 'MusicBrainz ne répond pas pour le moment.');
        } else if (axios.isAxiosError(e) && e.response?.status === 422) {
            invalid.value = e.response.data?.errors?.code?.[0] ?? "Ce code-barres n'est pas valide.";
        } else {
            setError('La recherche a échoué. Réessayez.');
        }
    } finally {
        if (controller === current) {
            loading.value = false;
        }
    }
}

onBeforeUnmount(() => controller?.abort());

const status = computed(() => {
    if (loading.value) {
        return 'Recherche en cours…';
    }
    if (error.value || invalid.value) {
        return error.value ?? invalid.value;
    }
    if (searched.value && editions.value.length === 0) {
        return "Aucune édition trouvée pour ce code-barres. Cherchez l'album par titre ci-dessous.";
    }
    if (editions.value.length > 1) {
        return `${editions.value.length} éditions trouvées : choisissez la vôtre.`;
    }
    return '';
});
</script>

<template>
    <div class="space-y-4">
        <div>
            <InputLabel for="barcode" value="Code-barres" />
            <!-- The search sits inside the create form: Enter searches instead of submitting it -->
            <div class="mt-1 flex gap-2" @keydown.enter.prevent="search">
                <TextInput
                    id="barcode"
                    type="search"
                    inputmode="numeric"
                    class="block w-full"
                    v-model="code"
                    autocomplete="off"
                    placeholder="Ex. 724385522925"
                    autofocus
                />
                <SecondaryButton :disabled="loading || code.trim() === ''" @click="search">Chercher</SecondaryButton>
            </div>
        </div>

        <p
            v-if="status"
            aria-live="polite"
            class="text-sm"
            :class="error || invalid ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400'"
        >
            {{ status }}
        </p>

        <ul v-if="editions.length" class="space-y-2">
            <li v-for="edition in editions" :key="edition.release_mbid">
                <button
                    type="button"
                    class="w-full rounded-md border border-gray-200 dark:border-gray-700 p-2 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                    @click="emit('select', edition)"
                >
                    <EditionSummary :edition="edition" />
                </button>
            </li>
        </ul>
    </div>
</template>
