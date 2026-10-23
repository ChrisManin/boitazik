<script setup lang="ts">
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { MusicBrainzResult } from '@/types';
import AlbumSummary from './AlbumSummary.vue';
import axios from 'axios';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const emit = defineEmits<{
    select: [result: MusicBrainzResult];
    // Server message when MusicBrainz is down, null once a search works again
    error: [message: string | null];
}>();

const DEBOUNCE_MS = 400;
const MIN_TITLE_LENGTH = 2;

const title = ref('');
const artist = ref('');
const results = ref<MusicBrainzResult[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
// Tells "no search yet" apart from "a search found nothing"
const searched = ref(false);

let timer: ReturnType<typeof setTimeout> | undefined;
let controller: AbortController | undefined;

function setError(message: string | null) {
    error.value = message;
    emit('error', message);
}

// Cancels the pending search and the request in flight, if any
function cancel() {
    clearTimeout(timer);
    controller?.abort();
    controller = undefined;
}

async function search(query: { title: string; artist: string }) {
    controller = new AbortController();
    const current = controller;
    loading.value = true;

    try {
        const params: Record<string, string> = { title: query.title };
        if (query.artist) {
            params.artist = query.artist;
        }
        const response = await axios.get<{ data: MusicBrainzResult[] }>(route('collection.search'), {
            params,
            signal: current.signal,
        });
        results.value = response.data.data;
        searched.value = true;
        setError(null);
    } catch (e) {
        // A newer search replaced this one: its outcome no longer matters
        if (axios.isCancel(e)) {
            return;
        }
        results.value = [];
        if (axios.isAxiosError(e) && e.response?.status === 503) {
            setError(e.response.data?.message ?? 'MusicBrainz ne répond pas pour le moment.');
        } else {
            // Validation errors (422) and anything else: just show no result
            searched.value = true;
        }
    } finally {
        if (controller === current) {
            loading.value = false;
        }
    }
}

watch([title, artist], ([newTitle, newArtist]) => {
    cancel();
    const query = { title: newTitle.trim(), artist: newArtist.trim() };

    if (query.title.length < MIN_TITLE_LENGTH) {
        results.value = [];
        loading.value = false;
        searched.value = false;
        return;
    }

    timer = setTimeout(() => search(query), DEBOUNCE_MS);
});

onBeforeUnmount(cancel);

const status = computed(() => {
    if (loading.value) {
        return 'Recherche en cours…';
    }
    if (error.value) {
        return error.value;
    }
    if (searched.value && results.value.length === 0) {
        return 'Aucun album trouvé';
    }
    if (searched.value) {
        return results.value.length === 1 ? '1 album trouvé' : `${results.value.length} albums trouvés`;
    }
    return '';
});
</script>

<template>
    <div class="space-y-4">
        <!-- The search sits inside the create form: Enter must not submit it -->
        <div class="grid gap-6 sm:grid-cols-2" @keydown.enter.prevent>
            <div>
                <InputLabel for="search_title" value="Titre" />
                <TextInput
                    id="search_title"
                    type="search"
                    class="mt-1 block w-full"
                    v-model="title"
                    autocomplete="off"
                />
            </div>

            <div>
                <InputLabel for="search_artist" value="Artiste (facultatif)" />
                <TextInput id="search_artist" type="search" class="mt-1 block w-full" v-model="artist" autocomplete="off" />
            </div>
        </div>

        <p
            aria-live="polite"
            class="text-sm"
            :class="error ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400'"
        >
            {{ status }}
        </p>

        <ul v-if="results.length" class="space-y-2">
            <li v-for="result in results" :key="result.mbid">
                <button
                    type="button"
                    class="w-full rounded-md border border-gray-200 dark:border-gray-700 p-2 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                    @click="emit('select', result)"
                >
                    <AlbumSummary :album="result" />
                </button>
            </li>
        </ul>
    </div>
</template>
