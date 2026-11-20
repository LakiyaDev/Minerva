import { useEffect, useMemo, useState, type FormEvent } from "react";
import { Bell, Check, Clock3, Feather, KeyRound, Mail, MessageSquare, Search, Trash2, UserRound } from "lucide-react";
import { Link } from "react-router";
import { ArticleCard } from "../components/ArticleCard";
import type { Article } from "../data";
import { deleteComment, getMyComments, type CommentDto } from "../services/commentService";
import type { VoteRecordDto } from "../services/votingService";
import { useApp } from "../store";

function formatShortDate(value?: string | null): string {
  if (!value) return "recorded";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "recorded";
  return date.toLocaleDateString("en-US", { day: "2-digit", month: "short" });
}

export function Dashboard() {
  const { articles, voted, votingStatus, voteRecords } = useApp();
  const published = articles.filter((a) => a.status === "published");
  const totalVotes = published.reduce((sum, a) => sum + a.votes, 0);
  const remaining = Math.max(0, published.length - voted.length);
  const percent = published.length
    ? Math.min(100, Math.round((voted.length / published.length) * 100))
    : 0;
  const isOpen = votingStatus ? votingStatus.is_open : true;

  return (
    <div className="dash-page">
      <header className="dash-heading">
        <div>
          <span className="eyebrow">your overview.</span>
          <h1>Good morning.</h1>
          <p>
            Your ballot is taking shape. There are {remaining} articles left to consider.
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
          <Link to="/my-votes">review my voting history →</Link>
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
          <h2>Your week in MINERVA.</h2>
        </div>
        <div>
          {voteRecords.length > 0 ? (
            <ul className="activity-list">
              {voteRecords.slice(0, 5).map((record) => (
                <li key={record.id}>
                  <Check size={14} />{" "}
                  <Link to={`/articles/${record.article?.slug || record.article_id}`}>
                    {record.article?.title ?? "Article vote"}
                  </Link>{" "}
                  <span>· voted {formatShortDate(record.created_at)}</span>
                </li>
              ))}
            </ul>
          ) : (
            <p className="empty-state-copy">No recent activity.</p>
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
  const [saved, setSaved] = useState(false);
  const submit = (e: FormEvent) => {
    e.preventDefault();
    setSaved(true);
    setTimeout(() => setSaved(false), 2000);
  };
  return (
    <div className="dash-page">
      <header className="dash-heading">
        <div>
          <span className="eyebrow">your identity.</span>
          <h1>Profile & security.</h1>
        </div>
      </header>
      <div className="profile-grid">
        <aside>
          <div className="large-avatar">—</div>
          <h2>Participant</h2>
          <p>Account details</p>
          <span>Member</span>
        </aside>
        <div>
          <form className="profile-form" onSubmit={submit}>
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
                <input placeholder="Full name" />
              </label>
              <label>
                Email address
                <input type="email" placeholder="name@example.com" />
              </label>
            </div>
            <label>
              Faculty
              <select defaultValue="">
                <option value="" disabled>Select faculty</option>
                <option>Faculty of Medical Sciences</option>
                <option>Faculty of Applied Sciences</option>
                <option>Faculty of Technology</option>
              </select>
            </label>
            <button className="btn">{saved ? "changes saved" : "save changes"}</button>
          </form>
          <form className="profile-form" onSubmit={(e) => e.preventDefault()}>
            <header>
              <KeyRound />
              <div>
                <h2>Change password</h2>
                <p>Use at least eight characters.</p>
              </div>
            </header>
            <label>
              Current password
              <input type="password" />
            </label>
            <div className="two-col">
              <label>
                New password
                <input type="password" />
              </label>
              <label>
                Confirm password
                <input type="password" />
              </label>
            </div>
            <button className="btn outline">update password</button>
          </form>
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
