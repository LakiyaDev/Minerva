import Echo from "laravel-echo";
import Pusher from "pusher-js";

// Make Pusher accessible on window for Echo
(window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher;

export type VotingResultBroadcastPayload = {
  period_id: string;
  article_id: string;
  vote_count: number;
  updated_at: string;
};

let echoInstance: Echo<"reverb"> | null = null;

export function getEcho(): Echo<"reverb"> | null {
  if (typeof window === "undefined") return null;
  if (echoInstance) return echoInstance;

  try {
    const key = (import.meta.env.VITE_REVERB_APP_KEY as string | undefined) || "minerva-reverb-key";
    const host = (import.meta.env.VITE_REVERB_HOST as string | undefined) || window.location.hostname;
    const port = import.meta.env.VITE_REVERB_PORT
      ? Number(import.meta.env.VITE_REVERB_PORT)
      : 8080;
    const scheme = (import.meta.env.VITE_REVERB_SCHEME as string | undefined) || "http";

    echoInstance = new Echo({
      broadcaster: "reverb",
      key,
      wsHost: host,
      wsPort: port,
      wssPort: port,
      forceTLS: scheme === "https",
      enabledTransports: ["ws", "wss"],
      authEndpoint: "/broadcasting/auth",
      auth: {
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
      },
    });

    return echoInstance;
  } catch (err) {
    console.warn("Unable to initialize real-time WebSocket connection:", err);
    return null;
  }
}

export function subscribeVotingResults(
  periodId: string,
  onUpdate: (payload: VotingResultBroadcastPayload) => void,
): () => void {
  const echo = getEcho();
  if (!echo || !periodId) {
    return () => {};
  }

  try {
    const channelName = `voting-results.${periodId}`;
    const channel = echo.private(channelName);

    channel.listen(".VotingResultsUpdated", (payload: VotingResultBroadcastPayload) => {
      onUpdate(payload);
    });

    channel.listen("VotingResultsUpdated", (payload: VotingResultBroadcastPayload) => {
      onUpdate(payload);
    });

    return () => {
      try {
        echo.leave(channelName);
      } catch {
        // Safe unsubscribe
      }
    };
  } catch (err) {
    console.warn("Failed to subscribe to voting-results channel:", err);
    return () => {};
  }
}

export function disconnectEcho(): void {
  if (echoInstance) {
    try {
      echoInstance.disconnect();
    } catch {
      // Ignore disconnect error
    }
    echoInstance = null;
  }
}
