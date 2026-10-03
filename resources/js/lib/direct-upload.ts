/**
 * Upload of a product file ahead of the product form.
 *
 * 1. Start it (POST /uploads/product-file). The app answers either
 *    - `direct`: a signed PUT URL for the cloud bucket (S3 / Google Cloud
 *      Storage / R2 …) — the file goes straight there; or
 *    - `chunked`: a session on this server — the file is sent in pieces
 *      smaller than PHP's upload limit, so big files work on any host.
 * 2. Send the file, reporting progress.
 * 3. Resolve with the token the product form submits to attach the file.
 */

export class DirectUploadError extends Error {
    /** True when the bucket refused the browser (usually missing CORS rules). */
    constructor(
        message: string,
        public readonly blockedByBucket = false,
    ) {
        super(message);
    }
}

/** Read Laravel's XSRF-TOKEN cookie so a manual JSON POST passes CSRF. */
function csrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

interface Presigned {
    mode: 'direct';
    token: string;
    url: string;
    headers: Record<string, string>;
}

interface ChunkedSession {
    mode: 'chunked';
    token: string;
    chunk_size: number;
}

async function readError(response: Response): Promise<DirectUploadError> {
    const body = await response.json().catch(() => ({}));
    const firstError = body?.errors ? Object.values(body.errors as Record<string, string[]>)[0]?.[0] : undefined;
    return new DirectUploadError(firstError ?? body?.message ?? `HTTP ${response.status}`);
}

async function start(file: File): Promise<Presigned | ChunkedSession> {
    const response = await fetch(route('uploads.product-file'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ name: file.name, size: file.size, type: file.type || null }),
    });

    if (!response.ok) throw await readError(response);

    return (await response.json()) as Presigned | ChunkedSession;
}

async function sendChunks(file: File, session: ChunkedSession, onProgress: (percent: number) => void, signal?: AbortSignal): Promise<void> {
    const total = Math.max(1, Math.ceil(file.size / session.chunk_size));

    for (let index = 0; index < total; index++) {
        if (signal?.aborted) throw new DirectUploadError('Upload cancelled');

        const body = new FormData();
        body.append('index', String(index));
        body.append('chunk', file.slice(index * session.chunk_size, (index + 1) * session.chunk_size), 'chunk');

        const response = await fetch(route('uploads.product-file.chunk', session.token), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': csrfToken() },
            body,
            signal,
        });
        if (!response.ok) throw await readError(response);

        onProgress(Math.round(((index + 1) / total) * 100));
    }
}

function put(file: File, target: Presigned, onProgress: (percent: number) => void, signal?: AbortSignal): Promise<void> {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('PUT', target.url);
        for (const [name, value] of Object.entries(target.headers)) {
            xhr.setRequestHeader(name, value);
        }

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) onProgress(Math.round((e.loaded / e.total) * 100));
        };
        xhr.onload = () =>
            xhr.status >= 200 && xhr.status < 300
                ? resolve()
                : reject(new DirectUploadError(`HTTP ${xhr.status} ${xhr.responseText.slice(0, 200)}`, true));
        // Status 0: the browser blocked the response — almost always CORS.
        xhr.onerror = () => reject(new DirectUploadError('Network error', true));
        xhr.onabort = () => reject(new DirectUploadError('Upload cancelled'));
        signal?.addEventListener('abort', () => xhr.abort());

        xhr.send(file);
    });
}

export async function directUpload(file: File, onProgress: (percent: number) => void, signal?: AbortSignal): Promise<string> {
    const target = await start(file);

    if (target.mode === 'chunked') {
        await sendChunks(file, target, onProgress, signal);
    } else {
        await put(file, target, onProgress, signal);
    }

    return target.token;
}
