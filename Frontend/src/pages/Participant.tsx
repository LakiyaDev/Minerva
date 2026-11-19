import { useState, type FormEvent } from "react";
import { Bell, Check, Clock3, Feather, KeyRound, Mail, Search, UserRound } from "lucide-react";
import { Link } from "react-router";
import { ArticleCard } from "../components/ArticleCard";
import { useApp } from "../store";

export function Dashboard() {
  const { articles, voted } = useApp();
  const published = articles.filter((a) => a.status === "published");
  const totalVotes = published.reduce((sum, a) => sum + a.votes, 0);
  const percent = published.length ? Math.round((voted.length / published.length) * 100) : 0;
  return (
    <div className="dash-page">
      <header className="dash-heading">
        <div>
          <span className="eyebrow">your overview.</span>
          <h1>Good morning.</h1>
          <p>
            Your ballot is taking shape. There are {published.length - voted.length} articles left
            to consider.
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
          [published.length - voted.length, "still to consider"],
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
            .filter((a) => !voted.includes(a.id) && a.voting === "open")
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
          <p className="empty-state-copy">No recent activity.</p>
        </div>
      </section>
    </div>
  );
}

export function MyVotes() {
  const { articles, voted } = useApp();
  const [filter, setFilter] = useState("All");
  const [search, setSearch] = useState("");
  const rows = articles.filter(
    (a) =>
      a.status === "published" &&
      a.title.toLowerCase().includes(search.toLowerCase()) &&
      (filter === "All" ||
        (filter === "Voted" && voted.includes(a.id)) ||
        (filter === "Not voted" && !voted.includes(a.id) && a.voting === "open") ||
        (filter === "Closed" && a.voting === "closed")),
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
        {rows.map((a) => (
          <article key={a.id}>
            <img src={a.image} alt="" />
            <div>
              <small>{a.category}</small>
              <Link to={`/articles/${a.id}`}>{a.title}</Link>
              <span>{a.author}</span>
            </div>
            {voted.includes(a.id) ? (
              <span className="history-status voted">
                <Check /> voted · 02 Dec
              </span>
            ) : a.voting === "closed" ? (
              <span className="history-status">
                <Clock3 /> closed
              </span>
            ) : (
              <Link className="history-status open" to={`/articles/${a.id}`}>
                read & vote →
              </Link>
            )}
          </article>
        ))}
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
