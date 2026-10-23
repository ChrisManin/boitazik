<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { MusicBrainzEdition, MusicBrainzResult, SelectOption } from '@/types';
import AlbumSummary from './Partials/AlbumSummary.vue';
import BarcodeSearch from './Partials/BarcodeSearch.vue';
import CopyFields from './Partials/CopyFields.vue';
import EditionSummary from './Partials/EditionSummary.vue';
import MusicBrainzSearch from './Partials/MusicBrainzSearch.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

defineProps<{
    formats: SelectOption[];
    conditions: SelectOption[];
}>();

// Empty strings are converted to null by Laravel.
// When release_mbid is set, the server imports the edition and ignores mbid, artist, title, year and format.
// When mbid is set, the server imports the album and ignores artist, title and year.
const form = useForm({
    release_mbid: '',
    mbid: '',
    artist: '',
    title: '',
    year: '',
    format: '',
    condition: '',
    acquired_at: '',
    notes: '',
});

// Search MusicBrainz by default, manual entry as a fallback
const mode = ref<'search' | 'manual'>('search');
const selected = ref<MusicBrainzResult | null>(null);
const selectedEdition = ref<MusicBrainzEdition | null>(null);
const searchError = ref<string | null>(null);

function select(result: MusicBrainzResult) {
    clearSelection();
    selected.value = result;
    form.mbid = result.mbid;
}

function selectEdition(edition: MusicBrainzEdition) {
    clearSelection();
    selectedEdition.value = edition;
    form.release_mbid = edition.release_mbid;
}

function clearSelection() {
    selected.value = null;
    selectedEdition.value = null;
    form.mbid = '';
    form.release_mbid = '';
    form.clearErrors('mbid', 'release_mbid');
}

// Unknown barcode: the title search is the next step
async function focusTitleSearch() {
    await nextTick();
    document.getElementById('search_title')?.focus();
}

function switchTo(newMode: 'search' | 'manual') {
    clearSelection();
    searchError.value = null;
    mode.value = newMode;
}

const linkClass =
    'text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 underline focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded-md';
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
                    <template v-if="mode === 'search'">
                        <div v-if="selectedEdition" class="space-y-2">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Édition choisie</p>
                            <div
                                class="flex items-center justify-between gap-4 rounded-md border border-indigo-300 dark:border-indigo-700 p-2"
                            >
                                <EditionSummary :edition="selectedEdition" />
                                <SecondaryButton @click="clearSelection">Changer</SecondaryButton>
                            </div>
                        </div>

                        <div v-else-if="selected" class="space-y-2">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Album choisi</p>
                            <div
                                class="flex items-center justify-between gap-4 rounded-md border border-indigo-300 dark:border-indigo-700 p-2"
                            >
                                <AlbumSummary :album="selected" />
                                <SecondaryButton @click="clearSelection">Changer</SecondaryButton>
                            </div>
                        </div>

                        <template v-else>
                            <BarcodeSearch @select="selectEdition" @not-found="focusTitleSearch" @error="searchError = $event" />

                            <div class="flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400" aria-hidden="true">
                                <span class="h-px flex-1 bg-gray-200 dark:bg-gray-700"></span>
                                ou par titre
                                <span class="h-px flex-1 bg-gray-200 dark:bg-gray-700"></span>
                            </div>

                            <MusicBrainzSearch @select="select" @error="searchError = $event" />
                        </template>

                        <InputError :message="form.errors.release_mbid" />
                        <InputError :message="form.errors.mbid" />

                        <!-- MusicBrainz is down: manual entry becomes the way forward -->
                        <div
                            v-if="searchError && !selected && !selectedEdition"
                            class="rounded-md bg-yellow-50 dark:bg-yellow-900/30 p-4 text-sm text-yellow-800 dark:text-yellow-200"
                        >
                            <p>Vous pouvez saisir l'album à la main en attendant.</p>
                            <SecondaryButton class="mt-3" @click="switchTo('manual')">Saisir l'album à la main</SecondaryButton>
                        </div>
                        <button v-else-if="!selected && !selectedEdition" type="button" :class="linkClass" @click="switchTo('manual')">
                            Je ne trouve pas mon album
                        </button>
                    </template>

                    <template v-else>
                        <button type="button" :class="linkClass" @click="switchTo('search')">
                            Rechercher sur MusicBrainz
                        </button>

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
                    </template>

                    <!-- The copy fields only make sense once the album is known -->
                    <CopyFields v-if="mode === 'manual'" :form="form" :formats="formats" :conditions="conditions">
                        <div>
                            <InputLabel for="year" value="Année de sortie" />
                            <TextInput id="year" type="number" class="mt-1 block w-full" v-model="form.year" />
                            <InputError class="mt-2" :message="form.errors.year" />
                        </div>
                    </CopyFields>
                    <CopyFields v-else-if="selected" :form="form" :formats="formats" :conditions="conditions" />
                    <CopyFields
                        v-else-if="selectedEdition"
                        :form="form"
                        :formats="formats"
                        :conditions="conditions"
                        hide-format
                    />

                    <div class="flex items-center justify-end gap-4">
                        <Link
                            :href="route('collection.index')"
                            class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 underline"
                        >
                            Annuler
                        </Link>
                        <PrimaryButton v-if="mode === 'manual' || selected || selectedEdition" :disabled="form.processing">Ajouter</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
