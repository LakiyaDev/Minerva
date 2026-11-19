import { useState, type FormEvent } from "react";
import {
  Area,
  AreaChart,
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import {
  Check,
  Edit3,
  Eye,
  FileText,
  Plus,
  Search,
  ToggleLeft,
  Trash2,
  UsersRound,
  Vote,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";
import { Link, useNavigate } from "react-router";
import { activity, participants, type Article } from "../data";
import { useApp } from "../store";

export function AdminOverview() {
  const { articles } = useApp();
  const published = articles.filter((a) => a.status === "published");
  const votes = articles.reduce((s, a) => s + a.votes, 0);
  const chart = [...published]
    .sort((a, b) => b.votes - a.votes)
    .slice(0, 6)
    .map((a) => ({ name: a.title.slice(0, 16) + "…", votes: a.votes }));
  const stats: Array<[LucideIcon, string | number, string, string]> = [
    [UsersRound, 0, "participants", "No data"],
    [FileText, published.length, "published articles", "No data"],
    [Vote, votes.toLocaleString(), "total votes", "No data"],
    [ToggleLeft, published.filter((a) => a.voting === "open").length, "open ballots", "No data"],
  ];
  return (
    <div className="admin-page">
      <PageHead
        eyebrow="editorial overview."
        title="Good morning, editor."
        action={
          <Link className="btn" to="/admin/create">
            <Plus /> new article
          </Link>
        }
      />
      <div className="admin-stats">
        {stats.map(([Icon, value, label, note]) => (
          <div key={label}>
            <Icon />
            <span>{label}</span>
            <strong>{value}</strong>
            <small>{note}</small>
          </div>
        ))}
      </div>
      <div className="charts-grid">
        <section>
          <header>
            <div>
              <span className="eyebrow">voting activity.</span>
              <h2>Votes over time</h2>
            </div>
            <select>
              <option>Last 6 weeks</option>
            </select>
          </header>
          <ResponsiveContainer width="100%" height={280}>
            <AreaChart data={activity}>
              <defs>
                <linearGradient id="bronze" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stopColor="#8A6D3B" stopOpacity=".35" />
                  <stop offset="100%" stopColor="#8A6D3B" stopOpacity="0" />
                </linearGradient>
              </defs>
              <CartesianGrid stroke="#e4dcc9" vertical={false} />
              <XAxis dataKey="day" axisLine={false} tickLine={false} />
              <YAxis axisLine={false} tickLine={false} />
              <Tooltip />
              <Area
                type="monotone"
                dataKey="votes"
                stroke="#8A6D3B"
                strokeWidth={2}
                fill="url(#bronze)"
              />
            </AreaChart>
          </ResponsiveContainer>
        </section>
        <section>
          <header>
            <div>
              <span className="eyebrow">leading essays.</span>
              <h2>Votes by article</h2>
            </div>
          </header>
          <ResponsiveContainer width="100%" height={280}>
            <BarChart data={chart} layout="vertical">
              <XAxis type="number" hide />
              <YAxis dataKey="name" type="category" axisLine={false} tickLine={false} width={115} />
              <Tooltip />
              <Bar dataKey="votes" fill="#8A6D3B" radius={[0, 4, 4, 0]} barSize={18} />
            </BarChart>
          </ResponsiveContainer>
        </section>
      </div>
      <section className="admin-activity">
        <header>
          <span className="eyebrow">recent activity.</span>
          <h2>What changed today</h2>
        </header>
        <p className="empty-state-copy">No recent activity.</p>
      </section>
    </div>
  );
}

function PageHead({
  eyebrow,
  title,
  action,
}: {
  eyebrow: string;
  title: string;
  action?: React.ReactNode;
}) {
  return (
    <header className="admin-heading">
      <div>
        <span className="eyebrow">{eyebrow}</span>
        <h1>{title}</h1>
      </div>
      {action}
    </header>
  );
}

export function AdminArticles() {
  const { articles, togglePublished, deleteArticle } = useApp();
  const [search, setSearch] = useState("");
  const rows = articles.filter((a) => a.title.toLowerCase().includes(search.toLowerCase()));
  return (
    <div className="admin-page">
      <PageHead
        eyebrow="content desk."
        title="Article management."
        action={
          <Link className="btn" to="/admin/create">
            <Plus /> create article
          </Link>
        }
      />
      <div className="table-tools">
        <label>
          <Search />
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search articles"
          />
        </label>
        <select>
          <option>All statuses</option>
          <option>Published</option>
          <option>Draft</option>
        </select>
        <select>
          <option>All categories</option>
        </select>
      </div>
      <div className="data-table article-admin-table">
        <div className="table-head">
          <span>Article</span>
          <span>Category</span>
          <span>Votes</span>
          <span>Publication</span>
          <span>Voting</span>
          <span>Actions</span>
        </div>
        {rows.map((a) => (
          <div className="table-row" key={a.id}>
            <div className="table-article">
              <img src={a.image} alt="" />
              <span>
                <strong>{a.title}</strong>
                <small>
                  {a.author} · {a.date}
                </small>
              </span>
            </div>
            <span>{a.category}</span>
            <strong>{a.votes}</strong>
            <button className={`status ${a.status}`} onClick={() => togglePublished(a.id)}>
              {a.status}
            </button>
            <span className={`status ${a.voting}`}>{a.voting}</span>
            <div className="row-actions">
              <Link to={`/articles/${a.id}`} aria-label="View">
                <Eye />
              </Link>
              <button aria-label="Edit">
                <Edit3 />
              </button>
              <button
                aria-label="Delete"
                onClick={() => confirm("Delete this article?") && deleteArticle(a.id)}
              >
                <Trash2 />
              </button>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

export function CreateArticle() {
  const { addArticle } = useApp();
  const navigate = useNavigate();
  const submit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const data = new FormData(e.currentTarget);
    const article: Article = {
      id: String(data.get("title"))
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-"),
      title: String(data.get("title")),
      author: String(data.get("author")),
      faculty: "University of Sri Jayewardenepura",
      category: String(data.get("category")),
      date: new Date().toLocaleDateString("en-US", {
        day: "2-digit",
        month: "short",
        year: "numeric",
      }),
      votes: 0,
      status: String(data.get("action")) === "publish" ? "published" : "draft",
      voting: "closed",
      image: "",
      excerpt: String(data.get("excerpt")),
    };
    addArticle(article);
    navigate("/admin/articles");
  };
  return (
    <div className="admin-page">
      <PageHead eyebrow="content desk." title="Create a new article." />
      <form className="editor-form" onSubmit={submit}>
        <section>
          <header>
            <span>01</span>
            <div>
              <h2>Basic information</h2>
              <p>Set the title, summary and author attribution.</p>
            </div>
          </header>
          <label>
            Article title
            <input name="title" required placeholder="A clear, compelling title" />
          </label>
          <label>
            Short description
            <textarea
              name="excerpt"
              required
              rows={3}
              placeholder="A concise introduction for cards and search results."
            />
          </label>
          <div className="two-col">
            <label>
              Author(s)
              <input name="author" required placeholder="Full name" />
            </label>
            <label>
              Category
              <select name="category">
                <option>AI in Medicine</option>
                <option>Bioinformatics</option>
                <option>Medical Devices</option>
                <option>Science Communication</option>
                <option>Sustainability</option>
              </select>
            </label>
          </div>
          <label>
            Article content
            <textarea rows={10} placeholder="Write or paste the full article here…" />
          </label>
        </section>
        <section>
          <header>
            <span>02</span>
            <div>
              <h2>Cover & supporting media</h2>
              <p>Add photography, gallery images and article documents.</p>
            </div>
          </header>
          <label className="dropzone">
            <Plus />
            <strong>Drop a cover image here</strong>
            <span>or browse your computer · JPG, PNG up to 8 MB</span>
            <input type="file" accept="image/*" />
          </label>
        </section>
        <section>
          <header>
            <span>03</span>
            <div>
              <h2>Publication</h2>
              <p>Save privately or make the article public.</p>
            </div>
          </header>
          <div className="publish-actions">
            <button className="btn outline" name="action" value="draft">
              save as draft
            </button>
            <button className="btn" name="action" value="publish">
              publish article
            </button>
          </div>
        </section>
      </form>
    </div>
  );
}

export function VotingManagement() {
  const { articles, toggleVoting } = useApp();
  return (
    <div className="admin-page">
      <PageHead eyebrow="ballot control." title="Voting management." />
      <div className="voting-grid">
        {articles
          .filter((a) => a.status === "published")
          .map((a) => (
            <article key={a.id}>
              <img src={a.image} alt="" />
              <div>
                <span className={`status ${a.voting}`}>{a.voting}</span>
                <h3>{a.title}</h3>
                <p>{a.votes} public votes</p>
              </div>
              <button
                className={`btn ${a.voting === "open" ? "outline" : ""}`}
                onClick={() =>
                  confirm(`${a.voting === "open" ? "Close" : "Open"} voting for this article?`) &&
                  toggleVoting(a.id)
                }
              >
                {a.voting === "open" ? "close voting" : "open voting"}
              </button>
            </article>
          ))}
      </div>
    </div>
  );
}

export function Results() {
  const { articles } = useApp();
  const sorted = [...articles]
    .filter((a) => a.status === "published")
    .sort((a, b) => b.votes - a.votes);
  const total = sorted.reduce((s, a) => s + a.votes, 0);
  const pie = [
    { name: "Medical & AI", value: 42 },
    { name: "Life sciences", value: 31 },
    { name: "Technology", value: 27 },
  ];
  return (
    <div className="admin-page">
      <PageHead eyebrow="the results." title="The count, made visible." />
      <div className="result-summary">
        <div>
          <strong>{total.toLocaleString()}</strong>
          <span>votes cast</span>
        </div>
        <div>
          <strong>0</strong>
          <span>participants</span>
        </div>
        <div>
          <strong>0%</strong>
          <span>participation</span>
        </div>
      </div>
      <div className="charts-grid">
        <section>
          <header>
            <div>
              <span className="eyebrow">ranking.</span>
              <h2>Article performance</h2>
            </div>
          </header>
          <ResponsiveContainer width="100%" height={300}>
            <BarChart
              data={sorted
                .slice(0, 6)
                .map((a) => ({ name: a.title.slice(0, 18) + "…", votes: a.votes }))}
              layout="vertical"
            >
              <XAxis type="number" hide />
              <YAxis dataKey="name" type="category" width={130} axisLine={false} tickLine={false} />
              <Tooltip />
              <Bar dataKey="votes" fill="#8A6D3B" radius={[0, 4, 4, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </section>
        <section>
          <header>
            <div>
              <span className="eyebrow">distribution.</span>
              <h2>Votes by field</h2>
            </div>
          </header>
          <ResponsiveContainer width="100%" height={300}>
            <PieChart>
              <Pie data={pie} dataKey="value" innerRadius={72} outerRadius={110} paddingAngle={3}>
                {["#8A6D3B", "#2E2A21", "#C9A227"].map((c) => (
                  <Cell key={c} fill={c} />
                ))}
              </Pie>
              <Tooltip />
            </PieChart>
          </ResponsiveContainer>
        </section>
      </div>
      <div className="results-table">
        {sorted.map((a, i) => (
          <div className={i === 0 ? "winner" : ""} key={a.id}>
            <strong>{String(i + 1).padStart(2, "0")}</strong>
            <span>
              <b>{a.title}</b>
              <small>{a.category}</small>
            </span>
            <em>{((a.votes / total) * 100).toFixed(1)}%</em>
            <b>{a.votes}</b>
            {i === 0 && <i>Most Popular · Leading</i>}
          </div>
        ))}
      </div>
    </div>
  );
}

export function Participants() {
  const [inactive, setInactive] = useState<string[]>([]);
  const [search, setSearch] = useState("");
  return (
    <div className="admin-page">
      <PageHead eyebrow="community." title="Participant management." />
      <div className="table-tools">
        <label>
          <Search />
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search participants"
          />
        </label>
      </div>
      <div className="data-table participant-table">
        <div className="table-head">
          <span>Participant</span>
          <span>Faculty</span>
          <span>Votes</span>
          <span>Status</span>
          <span>Action</span>
        </div>
        {participants
          .filter((p) => p[0].toString().toLowerCase().includes(search.toLowerCase()))
          .map((p) => (
            <div className="table-row" key={p[0]}>
              <div className="participant-name">
                <span>
                  {p[0]
                    .toString()
                    .split(" ")
                    .map((n) => n[0])
                    .slice(0, 2)
                    .join("")}
                </span>
                <div>
                  <strong>{p[0]}</strong>
                  <small>{p[1]}</small>
                </div>
              </div>
              <span>{p[2]}</span>
              <strong>{p[3]}</strong>
              <span className={`status ${inactive.includes(p[0] as string) ? "closed" : "open"}`}>
                {inactive.includes(p[0] as string) ? "inactive" : "active"}
              </span>
              <button
                className="text-button"
                onClick={() =>
                  setInactive((c) =>
                    c.includes(p[0] as string)
                      ? c.filter((n) => n !== p[0])
                      : [...c, p[0] as string],
                  )
                }
              >
                {inactive.includes(p[0] as string) ? "Activate" : "Deactivate"}
              </button>
            </div>
          ))}
      </div>
    </div>
  );
}

export function AdminSettings() {
  return (
    <div className="admin-page">
      <PageHead eyebrow="configuration." title="Platform settings." />
      <div className="settings-grid">
        <section>
          <h2>Editor profile</h2>
          <label>
            Display name
            <input placeholder="Editor name" />
          </label>
          <label>
            Contact email
            <input placeholder="name@example.com" />
          </label>
          <button className="btn">save profile</button>
        </section>
        <section>
          <h2>Notification preferences</h2>
          {[
            "New participant registrations",
            "New votes recorded",
            "Article status changes",
            "Weekly results digest",
          ].map((item) => (
            <label className="toggle-row" key={item}>
              <span>{item}</span>
              <input type="checkbox" defaultChecked />
            </label>
          ))}
        </section>
        <section className="platform-info">
          <span>MINERVA</span>
          <h2>Editorial Platform</h2>
          <p>
            IEEE Engineering in Medicine and Biology Society Student Branch Chapter, University of
            Sri Jayewardenepura.
          </p>
          <small>Backend integration ready</small>
        </section>
      </div>
    </div>
  );
}
