// Cliente da API no navegador. As chamadas vão para /api no próprio Next, que repassa ao Laravel.
// A sessão fica num cookie httpOnly; o Laravel exige o token CSRF (cookie XSRF-TOKEN) nas escritas.

export type FieldErrors = Record<string, string[]>;

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public errors: FieldErrors = {},
  ) {
    super(message);
  }

  /** Primeira mensagem do campo, para mostrar abaixo do input. */
  field(name: string): string | undefined {
    return this.errors[name]?.[0];
  }
}

function readCookie(name: string): string | undefined {
  const match = document.cookie.split("; ").find((c) => c.startsWith(`${name}=`));
  return match ? decodeURIComponent(match.slice(name.length + 1)) : undefined;
}

async function ensureCsrf(): Promise<string | undefined> {
  if (!readCookie("XSRF-TOKEN")) {
    await fetch("/sanctum/csrf-cookie", { credentials: "same-origin" });
  }
  return readCookie("XSRF-TOKEN");
}

export async function api<T = unknown>(path: string, init: { method?: string; body?: unknown } = {}): Promise<T> {
  const method = init.method ?? (init.body === undefined ? "GET" : "POST");
  const headers: Record<string, string> = { Accept: "application/json" };

  if (method !== "GET") {
    const token = await ensureCsrf();
    if (token) headers["X-XSRF-TOKEN"] = token;
  }
  // FormData (upload de imagem) segue como multipart; o resto vai em JSON.
  const isForm = init.body instanceof FormData;
  if (init.body !== undefined && !isForm) headers["Content-Type"] = "application/json";

  let res: Response;
  try {
    res = await fetch(`/api${path}`, {
      method,
      headers,
      credentials: "same-origin",
      body: init.body === undefined ? undefined : isForm ? (init.body as FormData) : JSON.stringify(init.body),
    });
  } catch {
    throw new ApiError(0, "Sem conexão com o servidor. Tente de novo.");
  }

  if (res.status === 204) return undefined as T;

  const data = await res.json().catch(() => null);
  if (!res.ok) {
    const message =
      res.status === 413
        ? "O arquivo é grande demais para enviar."
        : res.status === 429
        ? "Muitas tentativas. Espere um minuto e tente de novo."
        : res.status === 419
          ? "Sua sessão expirou. Recarregue a página."
          : res.status >= 500 || !data
            ? "O servidor não respondeu direito. Tente de novo."
            : (data.message ?? "Algo deu errado.");
    throw new ApiError(res.status, message, data?.errors ?? {});
  }
  return data as T;
}
