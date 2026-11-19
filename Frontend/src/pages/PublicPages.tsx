import { useMemo, useState } from "react";
import {
  ArrowLeft,
  ArrowRight,
  Check,
  Download,
  Feather,
  Grid2X2,
  List,
  Medal,
  Search,
  SlidersHorizontal,
  X,
} from "lucide-react";
import { Link, useNavigate, useParams } from "react-router";
import { ArticleCard } from "../components/ArticleCard";
import { useApp } from "../store";

export function Articles() {
  const { articles } = useApp();
  const [search, setSearch] = useState("");
  const [category, setCategory] = useState("All");
  const [sort, setSort] = useState("newest");
  const [visible, setVisible] = useState(6);
  const [list, setList] = useState(false);
  const categories = [
    "All",
    ...new Set(articles.filter((a) => a.status === "published").map((a) => a.category)),
  ];
  const filtered = useMemo(
    () =>
      articles
        .filter(
          (a) =>
            a.status === "published" &&
            (category === "All" || a.category === category) &&
            `${a.title} ${a.author}`.toLowerCase().includes(search.toLowerCase()),
        )
        .sort((a, b) => (sort === "votes" ? b.votes - a.votes : b.date.localeCompare(a.date))),
    [articles, search, category, sort],
  );
  return (
    <div className="page">
      <header className="editorial-header">
        <span className="eyebrow">
          the collected work · {articles.filter((a) => a.status === "published").length} essays
        </span>
        <h1>the library.</h1>
        <p>
          Original student perspectives on medicine, technology, living systems and the
          responsibility that connects them.
        </p>
      </header>
      <div className="filter-bar">
        <label className="search">
          <Search size={18} />
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search title or author"
            aria-label="Search articles"
          />
        </label>
        <div className="chips">
          {categories.map((item) => (
            <button
              key={item}
              className={category === item ? "active" : ""}
              onClick={() => setCategory(item)}
            >
              {item}
            </button>
          ))}
        </div>
        <select value={sort} onChange={(e) => setSort(e.target.value)} aria-label="Sort articles">
          <option value="newest">Newest</option>
          <option value="votes">Most voted</option>
        </select>
        <div className="view-toggle">
          <button
            className={!list ? "active" : ""}
            onClick={() => setList(false)}
            aria-label="Grid view"
          >
            <Grid2X2 size={17} />
          </button>
          <button
            className={list ? "active" : ""}
            onClick={() => setList(true)}
            aria-label="List view"
          >
            <List size={18} />
          </button>
        </div>
      </div>
      {filtered.length ? (
        <>
          <div className={`article-grid ${list ? "list" : ""}`}>
            {filtered.slice(0, visible).map((a, i) => (
              <ArticleCard article={a} index={i + 1} key={a.id} compact={list} />
            ))}
          </div>
          {visible < filtered.length && (
            <button className="load-more" onClick={() => setVisible((n) => n + 3)}>
              load more articles <ArrowRight size={16} />
            </button>
          )}
        </>
      ) : (
        <div className="empty-state">
          <span>◉</span>
          <h2>no articles match your search.</h2>
          <p>Try another phrase or clear your filters.</p>
          <button
            onClick={() => {
              setSearch("");
              setCategory("All");
            }}
          >
            clear filters
          </button>
        </div>
      )}
    </div>
  );
}

export function ArticleDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { articles, role, voted, requestVote, castVote } = useApp();
  const article = articles.find((a) => a.id === id);
  const [confirm, setConfirm] = useState(false);
  const [success, setSuccess] = useState(false);
  if (!article) return <NotFound />;
  const hasVoted = voted.includes(article.id);
  const vote = () => {
    if (role === "guest") return requestVote(article.id);
    if (castVote(article.id)) {
      setConfirm(false);
      setSuccess(true);
      setTimeout(() => setSuccess(false), 2800);
    }
  };
  return (
    <article className="article-detail">
      <div className="article-crumb">
        <button onClick={() => navigate(-1)}>
          <ArrowLeft size={15} /> back to library
        </button>
        <span>{article.category}</span>
      </div>
      <header>
        <span className="eyebrow">field note · {article.date}</span>
        <h1>{article.title}</h1>
        <div className="byline">
          <span>
            Written by <strong>{article.author}</strong>
          </span>
          <span>{article.faculty}</span>
          <span>8 minute read</span>
        </div>
      </header>
      <figure className="cover">
        <img src={article.image} alt="Scientific research connected to the article" />
        <figcaption>MINERVA editorial series · 2025</figcaption>
      </figure>
      <div className="article-layout">
        <div className="prose">
          <p className="lead">{article.excerpt}</p>
          <p>
            Scientific progress often arrives dressed as certainty. Yet the most meaningful advances
            begin with a question — patient, precise, and willing to challenge the assumptions
            beneath familiar systems. This essay follows that question from the laboratory into the
            lives it may eventually change.
          </p>
          <h2>Looking beyond the instrument</h2>
          <p>
            Technology is never simply a tool. It carries the priorities of its makers, the limits
            of its training, and the social conditions in which it is used. To understand its
            promise, we must also understand the people who might be overlooked by it.
          </p>
          <blockquote>
            “Wisdom is not knowing every answer. It is learning which questions deserve our deepest
            attention.”
          </blockquote>
          <p>
            For Sri Lankan students, this is more than an abstract debate. Resource constraints can
            create extraordinary ingenuity, but they also make responsible design essential. Systems
            should be explainable, resilient, and made for the realities of the communities they
            intend to serve.
          </p>
          <h2>A more public science</h2>
          <p>
            Communication is part of the research process, not an afterthought. When an idea can be
            explained without losing its nuance, it becomes available for scrutiny, collaboration
            and trust. That is the work this series hopes to celebrate.
          </p>
          <div className="documents">
            <h3>Further reading.</h3>
            <a href="#" onClick={(e) => e.preventDefault()}>
              <Download />
              <span>
                <strong>Article references</strong>
                <small>PDF · 1.8 MB</small>
              </span>
              <ArrowRight />
            </a>
          </div>
        </div>
        <aside className="vote-panel">
          <span className="eyebrow">the public ballot.</span>
          <div className="vote-count">
            <strong>{article.votes}</strong>
            <span>
              votes cast
              <br />
              rank #
              {[...articles]
                .sort((a, b) => b.votes - a.votes)
                .findIndex((a) => a.id === article.id) + 1}
            </span>
          </div>
          <div className={`status-line ${article.voting}`}>
            {article.voting === "open" ? "voting is open" : "voting has closed"}
          </div>
          {article.voting === "closed" ? (
            <button className="btn full" disabled>
              voting closed
            </button>
          ) : hasVoted ? (
            <div className="recorded">
              <Medal />
              <strong>vote recorded</strong>
              <small>Your choice is sealed.</small>
            </div>
          ) : (
            <button
              className="btn full"
              onClick={() => (role === "guest" ? vote() : setConfirm(true))}
            >
              {role === "guest" ? "sign in to vote" : "cast your vote"} <ArrowRight size={16} />
            </button>
          )}
          <p>One vote per participant. Votes are permanent and publicly counted.</p>
        </aside>
      </div>
      <section className="related">
        <span className="eyebrow">continue reading.</span>
        <div>
          {articles
            .filter((a) => a.id !== article.id && a.status === "published")
            .slice(0, 3)
            .map((a, i) => (
              <ArticleCard article={a} key={a.id} index={i + 1} compact />
            ))}
        </div>
      </section>
      {confirm && (
        <div className="modal-backdrop">
          <div className="confirm-modal">
            <button className="modal-close" onClick={() => setConfirm(false)}>
              <X />
            </button>
            <span className="eyebrow">your ballot.</span>
            <h2>Make this choice count?</h2>
            <p>You are voting for “{article.title}”. This action cannot be undone.</p>
            <div>
              <button className="btn outline" onClick={() => setConfirm(false)}>
                not yet
              </button>
              <button className="btn" onClick={vote}>
                seal my vote
              </button>
            </div>
          </div>
        </div>
      )}
      {success && (
        <div className="vote-toast">
          <Medal />
          <div>
            <strong>Vote recorded</strong>
            <span>Your voice is now part of the count.</span>
          </div>
        </div>
      )}
    </article>
  );
}

export function Leaderboard() {
  const { articles } = useApp();
  const sorted = [...articles]
    .filter((a) => a.status === "published")
    .sort((a, b) => b.votes - a.votes);
  const max = sorted[0]?.votes || 1;
  return (
    <div className="page leaderboard-page">
      <header className="editorial-header">
        <span className="eyebrow">live public count · refreshed just now</span>
        <h1>the standing.</h1>
        <p>
          Every voice, counted in public. The leading article will receive MINERVA’s Most Popular
          Article award.
        </p>
      </header>
      <div className="leader-table">
        <div className="leader-table-head">
          <span>Rank / Article</span>
          <span>Category</span>
          <span>Public votes</span>
        </div>
        {sorted.map((a, i) => (
          <Link to={`/articles/${a.id}`} key={a.id} className={i < 3 ? `medal medal-${i + 1}` : ""}>
            <strong>{String(i + 1).padStart(2, "0")}</strong>
            <div>
              <h3>{a.title}</h3>
              <small>{a.author}</small>
              <i style={{ width: `${max ? (a.votes / max) * 100 : 0}%` }} />
            </div>
            <span>{a.category}</span>
            <b>{a.votes}</b>
          </Link>
        ))}
      </div>
    </div>
  );
}

export function About() {
  return (
    <div className="about-page">
      <header className="about-hero">
        <div>
          <span className="eyebrow">our story.</span>
          <h1>
            Clarity is a form
            <br />
            of <em>generosity.</em>
          </h1>
        </div>
        <p>
          A year-round IEEE EMBS USJ Chapter initiative empowering students from diverse academic
          backgrounds to develop science communication and writing skills.
        </p>
      </header>
      <section className="manifesto">
        <span aria-hidden="true">"</span>
        <p>
          We believe scientific literacy grows not from simplifying difficult ideas, but from
          explaining them with care.
        </p>
      </section>
      <section className="pillars">
        <div className="section-title">
          <span className="eyebrow">three pillars.</span>
          <h2>
            A living programme,
            <br />
            not only a prize.
          </h2>
        </div>
        {[
          [
            "01",
            "Workshops",
            "Discipline-focused sessions in scientific writing, science communication and content development, led by academics and experienced writers.",
          ],
          [
            "02",
            "Article series",
            "Weekly, academically reviewed undergraduate articles, including an inter-university Open Category and recognition through Most Popular and Best Article awards.",
          ],
          [
            "03",
            "E-magazine",
            "A biannual record of the questions, breakthroughs and voices shaping our community.",
          ],
        ].map(([n, t, b]) => (
          <div className="pillar" key={n}>
            <strong>{n}</strong>
            <h3>{t}</h3>
            <p>{b}</p>
          </div>
        ))}
      </section>
      <section className="numbers">
        <div>
          <strong>—</strong>
          <span>student participants</span>
        </div>
        <div>
          <strong>—</strong>
          <span>countries represented by EMBS</span>
        </div>
        <div>
          <strong>—</strong>
          <span>EMBS members worldwide</span>
        </div>
      </section>
      <section className="about-timeline">
        <span className="eyebrow">project timeline.</span>
        {[
          "June · First workshop",
          "August · Theme-wise article publishing",
          "October · Inter-university Open Category begins",
          "November–December · Research and scientific writing workshop series",
          "December · Awarding ceremony",
        ].map((item, i) => (
          <div key={item}>
            <span>0{i + 1}</span>
            <h3>{item}</h3>
          </div>
        ))}
      </section>
    </div>
  );
}

export function NotFound() {
  return (
    <div className="not-found">
      <span>404</span>
      <h1>
        even Athena loses
        <br />
        her way sometimes.
      </h1>
      <p>This page has wandered beyond the map.</p>
      <Link className="btn" to="/">
        return home
      </Link>
    </div>
  );
}
