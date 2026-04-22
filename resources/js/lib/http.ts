function readCookie(name: string): string | null {
  if (typeof document === "undefined") {
    return null;
  }

  const match = document.cookie
    .split("; ")
    .find((cookie) => cookie.startsWith(`${name}=`));

  if (!match) {
    return null;
  }

  return decodeURIComponent(match.slice(name.length + 1));
}

export function csrfToken(): string {
  const cookieToken = readCookie("XSRF-TOKEN");

  if (cookieToken) {
    return cookieToken;
  }

  if (typeof document === "undefined") {
    return "";
  }

  return (
    document
      .querySelector('meta[name="csrf-token"]')
      ?.getAttribute("content") ?? ""
  );
}

export function jsonRequestHeaders(
  headers: Record<string, string> = {}
): Record<string, string> {
  const token = csrfToken();

  return {
    Accept: "application/json",
    "Content-Type": "application/json",
    "X-Requested-With": "XMLHttpRequest",
    ...(token !== ""
      ? {
          "X-CSRF-TOKEN": token,
          "X-XSRF-TOKEN": token,
        }
      : {}),
    ...headers,
  };
}
