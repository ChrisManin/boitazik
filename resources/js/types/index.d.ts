export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string;
}

export interface CollectionItem {
    id: number;
    title: string;
    cover_url: string | null;
    year: number | null;
    artists: string[];
    format: string | null;
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
};
