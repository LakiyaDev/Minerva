import { useEffect, useMemo, useState, type FormEvent } from "react";
import {
  Bell,
  Check,
  Clock3,
  Feather,
  KeyRound,
  LogOut,
  Mail,
  MessageSquare,
  Search,
  Shield,
  ShieldCheck,
  Trash2,
  UserRound,
} from "lucide-react";
import { Link } from "react-router";
import { ArticleCard } from "../components/ArticleCard";
import type { Article } from "../data";
import { deleteComment, getMyComments, type CommentDto } from "../services/commentService";
import { revokeOtherSessions } from "../services/authService";
import type { VoteRecordDto } from "../services/votingService";
import { useApp } from "../store";

function formatShortDate(value?: string | null): string {
  if (!value) return "recorded";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "recorded";
  return date.toLocaleDateString("en-US", { day: "2-digit", month: "short" });
}

export function Dashboard() {
  const { articles, voted, votingStatus, voteRecords, myComments, user } = useApp();
  const published = articles.filter((a) => a.status === "published");
  const totalVotes = published.reduce((sum, a) => sum + a.votes, 0);
  const remaining = Math.max(0, published.length - voted.length);
  const percent = published.length
    ? Math.min(100, Math.round((voted.length / published.length) * 100))
    : 0;
  const isOpen = votingStatus ? votingStatus.is_open : true;

  const recentActivity = useMemo(() => {
    type ActivityItem = {
      id: string;
      type: "vote" | "comment";
      title: string;
      slug: string;
      snippet?: string;
      date: string;
    };

    const items: ActivityItem[] = [];

    voteRecords.forEach((record) => {
      items.push({
        id: `vote-${record.id}`,
        type: "vote",
        title: record.article?.title ?? "Article ballot",
        slug: record.article?.slug || record.article_id,
        date: record.created_at,
      });
    });

    myComments.forEach((comment) => {
      items.push({
        id: `comment-${comment.id}`,
        type: "comment",
        title: comment.article?.title ?? "Article discussion",
        slug: comment.article?.slug || comment.article_id,
        snippet: comment.body,
        date: comment.created_at || new Date().toISOString(),
      });
    });

    items.sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime());
    return items.slice(0, 6);
  }, [voteRecords, myComments]);

  return (
    <div className="dash-page">
      <header className="dash-heading">
        <div>
          <span className="eyebrow">your overview.</span>
          <h1>Good morning, {user?.name ? user.name.split(" ")[0] : "reader"}.</h1>
          <p>
            Your ballot and community engagement are taking shape. There are {remaining} articles left to consider.
          </p>
        </div>
        <Link className="btn" to="/articles">
          continue reading
        </Link>
      </header>
      <div className="stats-row">
        {[
          [published.length, "published essays"],
          [voted.length, "votes recorded"],
          [myComments.length, "comments posted"],
          [remaining, "still to consider"],
          [totalVotes.toLocaleString(), "community votes"],
        ].map(([value, label]) => (
          <div className="stat-card" key={label}>
            <strong>{value}</strong>
            <span>{label}</span>
          </div>
        ))}
      </div>
      <section className="ballot-panel">
        <div>
          <span className="eyebrow">your ballot.</span>
          <h2>{percent}% complete</h2>
          <p>
            Each article deserves an attentive read. Your votes cannot be changed once recorded.
          </p>
          <div style={{ display: "flex", gap: "1rem", flexWrap: "wrap", marginTop: "0.5rem" }}>
            <Link to="/my-votes">review voting history ({voted.length}) →</Link>
            <Link to="/my-comments">view my comments ({myComments.length}) →</Link>
          </div>
        </div>
        <div
          className="progress-ring"
          style={{ "--progress": `${percent * 3.6}deg` } as React.CSSProperties}
        >
          <span>
            {voted.length}
            <small>of {published.length}</small>
          </span>
        </div>
      </section>
      <section className="dash-section">
        <header>
          <div>
            <span className="eyebrow">continue your ballot.</span>
            <h2>Open for your consideration.</h2>
          </div>
          <Link to="/articles">view the library →</Link>
        </header>
        <div className="dash-cards">
          {published
            .filter((a) => !voted.includes(a.id) && (isOpen && a.voting === "open"))
            .slice(0, 3)
            .map((a, i) => (
              <ArticleCard article={a} index={i + 1} key={a.id} />
            ))}
        </div>
      </section>
      <section className="activity-panel">
        <div>
          <span className="eyebrow">recent activity.</span>
          <h2>Your journey in MINERVA.</h2>
        </div>
        <div>
          {recentActivity.length > 0 ? (
            <ul className="activity-list">
              {recentActivity.map((item) => (
                <li key={item.id} style={{ alignItems: "flex-start", gap: "0.6rem" }}>
                  {item.type === "vote" ? (
                    <Check size={14} style={{ marginTop: "3px", color: "var(--bronze, #8a6d3b)" }} />
                  ) : (
                    <MessageSquare size={14} style={{ marginTop: "3px", color: "#6b705c" }} />
                  )}
                  <div style={{ display: "flex", flexDirection: "column", gap: "0.15rem" }}>
                    <div>
                      <Link to={`/articles/${item.slug}`}>
                        {item.title}
                      </Link>{" "}
                      <span style={{ fontSize: "0.75rem", opacity: 0.7 }}>
                        · {item.type === "vote" ? "voted" : "commented"} {formatShortDate(item.date)}
                      </span>
                    </div>
                    {item.snippet && (
                      <small style={{ opacity: 0.75, fontStyle: "italic", maxWidth: "450px" }}>
                        "{item.snippet.slice(0, 90)}{item.snippet.length > 90 ? "…" : ""}"
                      </small>
                    )}
                  </div>
                </li>
              ))}
            </ul>
          ) : (
            <p className="empty-state-copy">
              No recent activity. Cast a vote or share a perspective in the comments to see it recorded here.
            </p>
          )}
        </div>
      </section>
    </div>
  );
}

export function MyVotes() {
  const { articles, voted, voteRecords, votingStatus } = useApp();
  const [filter, setFilter] = useState("All");
  const [search, setSearch] = useState("");

  const recordMap = useMemo(() => {
    const map = new Map<string, VoteRecordDto>();
    voteRecords.forEach((record) => {
      map.set(record.article_id, record);
    });
    return map;
  }, [voteRecords]);

  const combinedArticles = useMemo(() => {
    const published = articles.filter((a) => a.status === "published");
    const knownIds = new Set(published.map((a) => a.id));
    const extras: Article[] = [];

    voteRecords.forEach((record) => {
      if (!knownIds.has(record.article_id) && record.article) {
        extras.push({
          id: record.article.id,
          slug: record.article.slug || record.article.id,
          title: record.article.title,
          author: record.article.author?.name || "Unknown author",
          category: record.article.category?.name || "Uncategorized",
          date: formatShortDate(record.created_at),
          abstract: "",
          excerpt: "",
          votes: 0,
          status: "published",
          voting: votingStatus?.is_open ? "open" : "closed",
          image: "",
        });
      }
    });

    return [...extras, ...published];
  }, [articles, voteRecords, votingStatus?.is_open]);

  const isWindowOpen = (article: Article) =>
    votingStatus ? votingStatus.is_open : article.voting === "open";

  const hasVotedFor = (articleId: string) =>
    recordMap.has(articleId) || voted.includes(articleId);

  const rows = combinedArticles.filter(
    (a) =>
      a.title.toLowerCase().includes(search.toLowerCase()) &&
      (filter === "All" ||
        (filter === "Voted" && hasVotedFor(a.id)) ||
        (filter === "Not voted" && !hasVotedFor(a.id) && isWindowOpen(a)) ||
        (filter === "Closed" && !isWindowOpen(a))),
  );

  return (
    <div className="dash-page">
      <header className="dash-heading">
        <div>
          <span className="eyebrow">your record.</span>
          <h1>My voting history.</h1>
          <p>A permanent record of the ideas you chose to champion.</p>
        </div>
      </header>
      <div className="history-filters">
        <label>
          <Search />
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search your ballot"
          />
        </label>
        <div>
          {["All", "Voted", "Not voted", "Closed"].map((item) => (
            <button
              className={filter === item ? "active" : ""}
              onClick={() => setFilter(item)}
              key={item}
            >
              {item}
            </button>
          ))}
        </div>
      </div>
      <div className="history-list">
        {rows.length === 0 ? (
          <p className="empty-state-copy">No matching articles in your voting history.</p>
        ) : (
          rows.map((a) => {
            const record = recordMap.get(a.id);
            const isVoted = Boolean(record) || voted.includes(a.id);
            const open = isWindowOpen(a);

            return (
              <article key={a.id}>
                {a.image ? (
                  <img src={a.image} alt="" />
                ) : (
                  <span className="article-placeholder" aria-hidden="true" />
                )}
                <div>
                  <small>{a.category}</small>
                  <Link to={`/articles/${a.slug || a.id}`}>{a.title}</Link>
                  <span>{a.author}</span>
                </div>
                {isVoted ? (
                  <span className="history-status voted">
                    <Check /> voted · {formatShortDate(record?.created_at)}
                  </span>
                ) : !open ? (
                  <span className="history-status">
                    <Clock3 /> {votingStatus?.status === "upcoming" ? "upcoming" : "closed"}
                  </span>
                ) : (
                  <Link className="history-status open" to={`/articles/${a.slug || a.id}`}>
                    read &amp; vote →
                  </Link>
                )}
              </article>
            );
          })
        )}
      </div>
    </div>
  );
}

export function Notifications() {
  const { markRead } = useApp();
  const [read, setRead] = useState(false);
  const notices: Array<[string, string, string]> = [];
  return (
    <div className="dash-page">
      <header className="dash-heading">
        <div>
          <span className="eyebrow">stay informed.</span>
          <h1>Notifications.</h1>
        </div>
        <button
          className="btn outline"
          onClick={() => {
            markRead();
            setRead(true);
          }}
        >
          mark all as read
        </button>
      </header>
      <div className="notification-list">
        {notices.length === 0 && <p className="empty-state-copy">No notifications.</p>}
        {notices.map(([title, body, time], i) => (
          <article className={read || i > 1 ? "read" : ""} key={title}>
            <span>
              <Bell />
            </span>
            <div>
              <strong>{title}</strong>
              <p>{body}</p>
              <small>{time}</small>
            </div>
            {!read && i < 2 && <i />}
          </article>
        ))}
      </div>
    </div>
  );
}

export function Profile() {
  const { user, updateProfile, logout } = useApp();
  const [name, setName] = useState(user?.name ?? "");
  const [saving, setSaving] = useState(false);
  const [revoking, setRevoking] = useState(false);
  const [feedback, setFeedback] = useState<{ type: "success" | "error"; text: string } | null>(null);

  useEffect(() => {
    if (user?.name) {
      setName(user.name);
    }
  }, [user?.name]);

  const handleUpdateName = async (e: FormEvent) => {
    e.preventDefault();
    if (!name.trim()) return;

    setSaving(true);
    setFeedback(null);
    const result = await updateProfile(name.trim());
    setSaving(false);

    if (result.ok) {
      setFeedback({ type: "success", text: "Your profile name has been updated successfully." });
    } else {
      setFeedback({ type: "error", text: result.message || "Unable to update profile name." });
    }
  };

  const handleRevokeSessions = async () => {
    if (
      !window.confirm(
        "This will sign out all your other active sessions across browsers and devices. Continue?",
      )
    ) {
      return;
    }

    setRevoking(true);
    setFeedback(null);
    try {
      const res = await revokeOtherSessions();
      setFeedback({
        type: "success",
        text: `Active sessions revoked successfully (${res.revoked_sessions} other session(s) ended).`,
      });
    } catch (err) {
      setFeedback({
        type: "error",
        text: err instanceof Error ? err.message : "Unable to revoke active sessions.",
      });
    } finally {
      setRevoking(false);
    }
  };

  const initials = user?.name
    ? user.name
        .split(" ")
        .map((n) => n[0])
        .filter(Boolean)
        .slice(0, 2)
        .join("")
        .toUpperCase()
    : "P";

  return (
    <div className="dash-page">
      <header className="dash-heading">
        <div>
          <span className="eyebrow">your identity.</span>
          <h1>Profile & security.</h1>
          <p>Manage your account identity and active sessions across the platform.</p>
        </div>
      </header>

      {feedback && (
        <div
          className={feedback.type === "success" ? "notice-banner" : "error-banner"}
          role="alert"
          style={{ marginBottom: "1.25rem" }}
        >
          <span>{feedback.text}</span>
        </div>
      )}

      <div className="profile-grid">
        <aside>
          <div className="large-avatar">{initials}</div>
          <h2>{user?.name ?? "Participant"}</h2>
          <p>{user?.email ?? "Signed in"}</p>
          <div style={{ display: "flex", flexWrap: "wrap", gap: "0.35rem", justifyContent: "center", marginTop: "0.5rem" }}>
            {user?.roles?.map((r) => (
              <span key={r} style={{ fontSize: "0.75rem", textTransform: "capitalize" }}>
                {r}
              </span>
            )) ?? <span>Member</span>}
          </div>
        </aside>
        <div>
          <form className="profile-form" onSubmit={handleUpdateName}>
            <header>
              <UserRound />
              <div>
                <h2>Personal information</h2>
                <p>Keep your participant details current.</p>
              </div>
            </header>
            <div className="two-col">
              <label>
                Full name
                <input
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder="Full name"
                  required
                />
              </label>
              <label>
                Email address
                <input
                  type="email"
                  value={user?.email ?? ""}
                  disabled
                  title="Email cannot be changed directly"
                  style={{ opacity: 0.7, cursor: "not-allowed" }}
                />
              </label>
            </div>
            <p style={{ fontSize: "0.8rem", opacity: 0.7, margin: "0.25rem 0 0.75rem" }}>
              Your email is the verified primary identifier for your ballots and access tokens.
            </p>
            <button className="btn" disabled={saving}>
              {saving ? "saving…" : "save changes"}
            </button>
          </form>

          <section
            className="profile-form"
            style={{ marginTop: "1.5rem" }}
          >
            <header>
              <ShieldCheck />
              <div>
                <h2>Account security &amp; sessions</h2>
                <p>Monitor your authentication state and manage active tokens.</p>
              </div>
            </header>
            <div style={{ display: "grid", gap: "0.75rem", padding: "0.5rem 0" }}>
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "0.75rem", background: "rgba(128,128,128,0.06)", borderRadius: "6px" }}>
                <div>
                  <strong>Two-Factor Authentication (TOTP)</strong>
                  <p style={{ margin: "0.2rem 0 0", fontSize: "0.82rem", opacity: 0.75 }}>
                    {user?.two_factor_enabled
                      ? "Two-factor authentication is active and protecting your account."
                      : "Staff members require MFA for editorial and management actions."}
                  </p>
                </div>
                <span style={{ fontSize: "0.85rem", fontWeight: 600, color: user?.two_factor_enabled ? "#2e7d32" : "#8a6d3b" }}>
                  {user?.two_factor_enabled ? "Enabled" : "Not configured"}
                </span>
              </div>

              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "0.75rem", background: "rgba(128,128,128,0.06)", borderRadius: "6px" }}>
                <div>
                  <strong>Active Sessions</strong>
                  <p style={{ margin: "0.2rem 0 0", fontSize: "0.82rem", opacity: 0.75 }}>
                    Terminate all other logged-in browser sessions and mobile devices.
                  </p>
                </div>
                <button
                  type="button"
                  className="btn outline"
                  onClick={() => void handleRevokeSessions()}
                  disabled={revoking}
                >
                  {revoking ? "revoking…" : "revoke other sessions"}
                </button>
              </div>

              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "0.75rem", background: "rgba(128,128,128,0.06)", borderRadius: "6px" }}>
                <div>
                  <strong>Sign Out</strong>
                  <p style={{ margin: "0.2rem 0 0", fontSize: "0.82rem", opacity: 0.75 }}>
                    End your current session safely on this device.
                  </p>
                </div>
                <button
                  type="button"
                  className="btn outline"
                  onClick={() => void logout()}
                >
                  <LogOut size={14} /> log out
                </button>
              </div>
            </div>
          </section>
        </div>
      </div>
    </div>
  );
}

export function MyComments() {
  const [comments, setComments] = useState<CommentDto[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [deletingId, setDeletingId] = useState<string | null>(null);

  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setError(null);

    getMyComments(1, controller.signal)
      .then((res) => {
        if (!controller.signal.aborted) setComments(res.data);
      })
      .catch((err) => {
        if (controller.signal.aborted) return;
        setError(err instanceof Error ? err.message : "Unable to load your comments.");
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });

    return () => controller.abort();
  }, []);

  const handleDelete = async (commentId: string) => {
    if (!window.confirm("Delete this comment?")) return;

    setDeletingId(commentId);
    setError(null);
    try {
      await deleteComment(commentId);
      setComments((prev) => prev.filter((item) => item.id !== commentId));
    } catch (err) {
      setError(err instanceof Error ? err.message : "Unable to delete comment.");
    } finally {
      setDeletingId(null);
    }
  };

  return (
    <div className="dash-page">
      <header className="dash-heading">
        <div>
          <span className="eyebrow">your discussion history.</span>
          <h1>My comments.</h1>
        </div>
        <Link className="btn outline" to="/articles">
          browse articles
        </Link>
      </header>

      {error && (
        <div className="error-banner" role="alert" style={{ marginBottom: "1rem" }}>
          <span>{error}</span>
        </div>
      )}

      {loading ? (
        <p className="empty-state-copy">Loading your comments…</p>
      ) : comments.length === 0 ? (
        <p className="empty-state-copy">You have not posted any comments yet.</p>
      ) : (
        <div style={{ display: "grid", gap: "0.9rem" }}>
          {comments.map((comment) => (
            <article
              key={comment.id}
              style={{
                padding: "1rem 1.2rem",
                border: "1px solid rgba(128, 128, 128, 0.2)",
                borderRadius: "8px",
                display: "grid",
                gap: "0.5rem",
              }}
            >
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", gap: "0.75rem" }}>
                <div style={{ display: "flex", alignItems: "center", gap: "0.5rem" }}>
                  <MessageSquare size={15} />
                  {comment.article ? (
                    <Link to={`/articles/${comment.article.slug}`}>
                      <strong>{comment.article.title}</strong>
                    </Link>
                  ) : (
                    <strong>Article</strong>
                  )}
                  <small style={{ opacity: 0.65 }}>{formatShortDate(comment.created_at)}</small>
                </div>
                {!comment.is_removed && (
                  <button
                    type="button"
                    className="btn outline"
                    disabled={deletingId === comment.id}
                    onClick={() => void handleDelete(comment.id)}
                  >
                    <Trash2 size={14} /> {deletingId === comment.id ? "deleting…" : "delete"}
                  </button>
                )}
              </div>
              {comment.is_removed ? (
                <p style={{ margin: 0, fontStyle: "italic", opacity: 0.7 }}>{comment.body}</p>
              ) : (
                <p style={{ margin: 0, whiteSpace: "pre-wrap" }}>{comment.body}</p>
              )}
            </article>
          ))}
        </div>
      )}
    </div>
  );
}
