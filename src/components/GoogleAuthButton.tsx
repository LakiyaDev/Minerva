import { useState } from "react";
import { API_BASE_URL } from "../lib/api";

type RedirectResponse = {
  data?: {
    authorization_url?: unknown;
  };
};

function isRedirectResponse(value: unknown): value is RedirectResponse {
  return typeof value === "object" && value !== null && "data" in value;
}

export function GoogleAuthButton() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const startGoogleAuth = async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await fetch(`${API_BASE_URL}/api/v1/auth/google/redirect`, {
        credentials: "include",
        headers: { Accept: "application/json" },
      });
      const payload: unknown = await response.json();

      if (!response.ok || !isRedirectResponse(payload)) {
        throw new Error("The Google sign-in request was rejected.");
      }

      const authorizationUrl = payload.data?.authorization_url;
      if (typeof authorizationUrl !== "string") {
        throw new Error("The Google sign-in URL was missing.");
      }

      const target = new URL(authorizationUrl);
      if (target.origin !== "https://accounts.google.com") {
        throw new Error("The Google sign-in URL was not trusted.");
      }

      window.location.assign(target.href);
    } catch (error) {
      console.error("Could not start Google sign-in.", error);
      setError("Google sign-in is unavailable right now. Please try again.");
      setLoading(false);
    }
  };

  return (
    <div className="google-auth">
      <button
        className="google-auth-button"
        type="button"
        onClick={startGoogleAuth}
        disabled={loading}
      >
        <svg aria-hidden="true" viewBox="0 0 48 48">
          <path
            fill="#4285F4"
            d="M43.6 24.5c0-1.4-.1-2.8-.4-4.1H24v7.8h11a9.4 9.4 0 0 1-4.1 6.1v5.1h6.6c3.9-3.6 6.1-8.8 6.1-14.9Z"
          />
          <path
            fill="#34A853"
            d="M24 44c5.5 0 10.1-1.8 13.5-4.9l-6.6-5.1c-1.8 1.2-4.1 1.9-6.9 1.9-5.3 0-9.8-3.6-11.4-8.5H5.8v5.3C9.2 39.4 16.1 44 24 44Z"
          />
          <path
            fill="#FBBC05"
            d="M12.6 27.4a12 12 0 0 1 0-6.8v-5.3H5.8a20 20 0 0 0 0 17.4l6.8-5.3Z"
          />
          <path
            fill="#EA4335"
            d="M24 12.1c3 0 5.7 1 7.8 3.1l5.8-5.8C34.1 6.1 29.5 4 24 4 16.1 4 9.2 8.6 5.8 15.3l6.8 5.3c1.6-4.9 6.1-8.5 11.4-8.5Z"
          />
        </svg>
        {loading ? "Connecting to Google…" : "Continue with Google"}
      </button>
      {error && (
        <p className="google-auth-error" role="alert">
          {error}
        </p>
      )}
    </div>
  );
}
