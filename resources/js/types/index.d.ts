export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string;
}

// An artist credited on an album, enough to link to their page
export interface ArtistRef {
    id: number;
    name: string;
}

export interface CollectionItem {
    id: number;
    title: string;
    cover_url: string | null;
    year: number | null;
    artists: ArtistRef[];
    format: string | null;
}

// A copy as loaded in the edit form: format holds the enum value, not its label
export interface EditableCollectionItem extends CollectionItem {
    condition: string | null;
    acquired_at: string | null;
    notes: string | null;
}

export interface ArtistDetail extends ArtistRef {
    // ISO 3166-1 alpha-2 code
    country: string | null;
    image_url: string | null;
}

export interface DiscographyAlbum {
    id: number;
    title: string;
    year: number | null;
    cover_url: string | null;
    // In the current user's collection
    owned: boolean;
}

// The artist's albums of one type, sorted by year
export interface DiscographyGroup {
    type: string;
    label: string;
    albums: DiscographyAlbum[];
}

// An enum case offered in a form select
export interface SelectOption {
    value: string;
    label: string;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

// Shape of a paginated API Resource collection
export interface Paginated<T> {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: PaginationLink[];
    };
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    auth: {
        user: User;
    };
    flash: {
        success: string | null;
    };
};

// A release group found on MusicBrainz, as returned by collection.search
export interface MusicBrainzResult {
    mbid: string;
    title: string;
    artists: string[];
    year: number | null;
    // Already a French label (Album, EP, Single…)
    type: string;
    // Built without checking the image exists: it may 404
    cover_url: string;
}

// One physical edition found by barcode
export interface MusicBrainzEdition {
    release_mbid: string;
    album: MusicBrainzResult;
    // Already a French label (CD, Vinyle…)
    format: string;
    label: string | null;
    catalog_number: string | null;
    country: string | null;
    year: number | null;
    barcode: string | null;
}
