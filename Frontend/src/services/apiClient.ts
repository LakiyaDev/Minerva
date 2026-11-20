/**
 * Typed API client for the Minerva backend.
 *
 * Browser sessions use the Sanctum HttpOnly cookie, so every request sends
 * credentials. Mutating requests additionally need the CSRF token that
 * Sanctum exposes through the XSRF-TOKEN cookie.
 */

export type ApiErrorPayload = {
  message?: string;
  code?: string;
  request_id?: string;
  errors?: Record<string, string[]>;
};

export class ApiError extends Error {
  readonly status: number;
  readonly code: string;
  readonly errors: Record<string, string[]>;
  readonly requestId?: string;

  constructor(status: number, payload: ApiErrorPayload) {
    super(payload.message || "Request failed.");
    this.name = "ApiError";
    this.status = status;
    this.code = payload.code || "request_failed";
    this.errors = payload.errors || {};
    this.requestId = payload.request_id;
  }

  /** Field-level message for a given input, if the API returned one. */
  fieldError(field: string): string | undefined {
    return this.errors[field]?.[0];
  }
}

/** The API could not be reached at all (backend down, CORS, offline). */
export class ApiUnavailableError extends Error {
  readonly cause?: unknown;

  constructor(message = "The MINERVA API is unreachable.", cause?: unknown) {
    super(message);
    this.name = "ApiUnavailableError";
    this.cause = cause;
  }
}

export type ApiEnvelope<T> = {
  data: T;
  meta?: {
    current_page?: number;
    per_page?: number;
    total?: number;
    last_page?: number;
  };
};

const API_BASE = "/api/v1";

function readCookie(name: string): string | null {
  if (typeof document === "undefined") return null;

  const match = document.cookie.match(new RegExp(`(^|;\\s*)${name}=([^;]*)`));

  return match ? decodeURIComponent(match[2]) : null;
}

let csrfCookieRequested = false;

/**
 * Ask Sanctum for the CSRF cookie once per session. The cookie is HttpOnly
 * for the session itself, but XSRF-TOKEN is deliberately readable by the SPA.
 */
async function ensureCsrfCookie(): Promise<void> {
  if (csrfCookieRequested) return;

  csrfCookieRequested = true;

  try {
    await fetch("/sanctum/csrf-cookie", {
      credentials: "include",
      headers: { Accept: "application/json" },
    });
  } catch {
    // A failed CSRF handshake surfaces later as a normal API error.
  }
}

export type RequestOptions = {
  method?: "GET" | "POST" | "PATCH" | "PUT" | "DELETE";
  body?: unknown;
  query?: Record<string, string | number | boolean | undefined | null>;
  signal?: AbortSignal;
};

export async function apiRequest<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const method = options.method ?? "GET";
  const headers: Record<string, string> = { Accept: "application/json" };
  const isFormData = typeof FormData !== "undefined" && options.body instanceof FormData;

  if (options.body !== undefined && !isFormData) {
    headers["Content-Type"] = "application/json";
  }

  if (method !== "GET") {
    await ensureCsrfCookie();
    const token = readCookie("XSRF-TOKEN");
    if (token) headers["X-XSRF-TOKEN"] = token;
  }

  let url = `${API_BASE}${path}`;

  if (options.query) {
    const params = new URLSearchParams();
    Object.entries(options.query).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== "") {
        params.set(key, String(value));
      }
    });
    const query = params.toString();
    if (query) url += `?${query}`;
  }

  let response: Response;

  try {
    const body =
      options.body === undefined
        ? undefined
        : isFormData
          ? (options.body as FormData)
          : JSON.stringify(options.body);

    response = await fetch(url, {
      method,
      headers,
      credentials: "include",
      signal: options.signal,
      body,
    });
  } catch (error) {
    throw new ApiUnavailableError(undefined, error);
  }

  if (response.status === 204) {
    return undefined as T;
  }

  const text = await response.text();
  let payload: unknown = null;

  if (text) {
    try {
      payload = JSON.parse(text);
    } catch {
      payload = { message: text };
    }
  }

  if (!response.ok) {
    throw new ApiError(response.status, (payload as ApiErrorPayload) || {});
  }

  return payload as T;
}

/** Convenience helper for the standard { data, meta } envelope. */
export async function apiRequestEnvelope<T>(
  path: string,
  options: RequestOptions = {},
): Promise<ApiEnvelope<T>> {
  const payload = await apiRequest<ApiEnvelope<T>>(path, options);

  return { data: payload.data, meta: payload.meta };
}
