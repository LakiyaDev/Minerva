import { useEffect, useMemo, useRef, useState } from "react";
import {
  ArrowLeft,
  ArrowRight,
  BookOpen,
  Check,
  Feather,
  MousePointer2,
  UserRound,
} from "lucide-react";
import {
  AnimatePresence,
  animate,
  motion,
  useMotionValue,
  useReducedMotion,
  useTransform,
} from "motion/react";
import { Link } from "react-router";
import type { Article } from "../data";
import { useApp } from "../store";

const chapters = ["prologue", "featured", "library", "the ballot", "our story"];
type TurnState = { to: number; direction: 1 | -1; mode: "auto" | "drag" };

export function Home() {
  const { articles, role } = useApp();
  const showVotes = role !== "guest";
  const published = articles.filter((article) => article.status === "published");
  const leaders = useMemo(
    () => [...published].sort((a, b) => b.votes - a.votes).slice(0, 5),
    [published],
  );
  const [opened, setOpened] = useState(false);
  const [page, setPage] = useState(0);
  const [turning, setTurning] = useState<TurnState | null>(null);
  const dragState = useRef<{
    pointerId: number;
    startX: number;
    width: number;
    direction: 1 | -1;
    to: number;
  } | null>(null);
  const rotation = useMotionValue(0);
  const reducedMotion = useReducedMotion();
  const curl = useTransform(rotation, [-180, -95, 0, 95, 180], [0, 0.8, 0, 0.8, 0]);
  const leafScale = useTransform(rotation, [-180, -90, 0, 90, 180], [1, 0.965, 1, 0.965, 1]);

  useEffect(() => {
    document.body.classList.add("book-ruins-theme");
    return () => document.body.classList.remove("book-ruins-theme");
  }, []);

  const turnTo = (next: number) => {
    if (!opened) return;
    const bounded = Math.max(0, Math.min(chapters.length - 1, next));
    if (bounded === page || turning) return;
    const turnDirection = bounded > page ? 1 : -1;
    if (reducedMotion) {
      setPage(bounded);
      return;
    }
    rotation.set(0);
    setTurning({ to: bounded, direction: turnDirection, mode: "auto" });
  };

  useEffect(() => {
    const onKeyDown = (event: KeyboardEvent) => {
      if (!opened && ["Enter", " ", "ArrowRight"].includes(event.key)) {
        event.preventDefault();
        setOpened(true);
        return;
      }
      if (event.key === "ArrowRight") turnTo(page + 1);
      if (event.key === "ArrowLeft") turnTo(page - 1);
    };
    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [opened, page, turning]);

  useEffect(() => {
    if (!turning || turning.mode !== "auto") return;
    const target = turning.direction > 0 ? -180 : 180;
    const controls = animate(rotation, target, {
      duration: 0.92,
      ease: [0.32, 0.02, 0.18, 1],
    });
    controls.then(() => {
      setPage(turning.to);
      setTurning(null);
      rotation.set(0);
    });
    return () => controls.stop();
  }, [turning, rotation]);

  const spreads = [
    <OpeningSpread key="opening" />,
    <FeaturedSpread key="featured" articles={published.slice(0, 3)} showVotes={showVotes} />,
    <LibrarySpread key="library" articles={published.slice(3, 7)} />,
    <BallotSpread key="ballot" leaders={leaders} showVotes={showVotes} />,
    <StorySpread key="story" />,
  ];

  return (
    <div className="book-landing">
      <div className="book-atmosphere" aria-hidden="true">
        <span />
        <span />
        <span />
      </div>

      <div className="book-intro">
        <span className="eyebrow">the minerva volume · edition i</span>
        <p>Use the page edges, arrow keys, or swipe to read.</p>
      </div>

      <section
        className={`book-stage ${opened ? "volume-open" : "volume-closed"}`}
        aria-label={opened ? `MINERVA digital book, ${chapters[page]}` : "Closed MINERVA book"}
      >
        <AnimatePresence>
          {!opened && <ClosedVolume onOpen={() => setOpened(true)} />}
        </AnimatePresence>

        <motion.div
          className={`book-pages ${turning ? "is-turning" : ""}`}
          initial={false}
          animate={{
            opacity: opened ? 1 : 0,
            scaleX: opened ? 1 : 0.5,
            scaleY: opened ? 1 : 0.92,
          }}
          transition={{
            duration: 1.05,
            ease: [0.22, 0.72, 0.18, 1],
            delay: opened ? 0.14 : 0,
          }}
          style={{ pointerEvents: opened ? "auto" : "none" }}
          onPointerDown={(event) => {
            if (turning || (event.target as HTMLElement).closest("a, button")) return;
            const rect = event.currentTarget.getBoundingClientRect();
            const localX = event.clientX - rect.left;
            let turnDirection: 1 | -1 | null = null;
            if (localX > rect.width * 0.68 && page < chapters.length - 1) turnDirection = 1;
            if (localX < rect.width * 0.32 && page > 0) turnDirection = -1;
            if (!turnDirection) return;
            const to = page + turnDirection;
            event.currentTarget.setPointerCapture(event.pointerId);
            dragState.current = {
              pointerId: event.pointerId,
              startX: event.clientX,
              width: rect.width,
              direction: turnDirection,
              to,
            };
            rotation.set(0);
            setTurning({ to, direction: turnDirection, mode: "drag" });
          }}
          onPointerMove={(event) => {
            const drag = dragState.current;
            if (!drag || drag.pointerId !== event.pointerId) return;
            const distance =
              drag.direction > 0 ? drag.startX - event.clientX : event.clientX - drag.startX;
            const progress = Math.max(0, Math.min(1, distance / (drag.width * 0.62)));
            rotation.set((drag.direction > 0 ? -180 : 180) * progress);
          }}
          onPointerUp={async (event) => {
            const drag = dragState.current;
            if (!drag || drag.pointerId !== event.pointerId) return;
            dragState.current = null;
            const shouldComplete = Math.abs(rotation.get()) > 52;
            if (reducedMotion) {
              if (shouldComplete) setPage(drag.to);
              setTurning(null);
              rotation.set(0);
              return;
            }
            await animate(rotation, shouldComplete ? (drag.direction > 0 ? -180 : 180) : 0, {
              duration: shouldComplete ? 0.48 : 0.34,
              ease: shouldComplete ? [0.22, 0.72, 0.18, 1] : [0.2, 0.8, 0.3, 1],
            });
            if (shouldComplete) setPage(drag.to);
            setTurning(null);
            rotation.set(0);
          }}
          onPointerCancel={() => {
            dragState.current = null;
            animate(rotation, 0, { duration: 0.25 }).then(() => {
              setTurning(null);
              rotation.set(0);
            });
          }}
        >
          <div className="book-block left-block" aria-hidden="true" />
          <div className="book-block right-block" aria-hidden="true" />
          <div className="book-gutter" aria-hidden="true" />

          <div className="book-spread">{spreads[page]}</div>

          {turning && (
            <>
              <div
                className={`page-beneath ${turning.direction > 0 ? "right" : "left"}`}
                aria-hidden="true"
              >
                <SpreadCrop
                  spread={spreads[turning.to]}
                  side={turning.direction > 0 ? "right" : "left"}
                />
              </div>
              <motion.div
                className={`turning-leaf ${turning.direction > 0 ? "forward" : "backward"}`}
                style={
                  { rotateY: rotation, scaleX: leafScale, "--curl": curl } as React.CSSProperties
                }
                aria-hidden="true"
              >
                <div className="leaf-face leaf-front">
                  <SpreadCrop
                    spread={spreads[page]}
                    side={turning.direction > 0 ? "right" : "left"}
                  />
                </div>
                <div className="leaf-face leaf-back">
                  <SpreadCrop
                    spread={spreads[turning.to]}
                    side={turning.direction > 0 ? "left" : "right"}
                  />
                </div>
                <div className="leaf-curl" />
              </motion.div>
              <motion.div
                className={`moving-page-shadow ${turning.direction > 0 ? "forward" : "backward"}`}
                style={{ opacity: curl }}
                aria-hidden="true"
              />
            </>
          )}

          {page > 0 && (
            <button
              className="page-edge page-edge-left"
              onClick={() => turnTo(page - 1)}
              aria-label={`Turn back to ${chapters[page - 1]}`}
            >
              <ArrowLeft />
              <span>previous</span>
            </button>
          )}
          {page < chapters.length - 1 && (
            <button
              className="page-edge page-edge-right"
              onClick={() => turnTo(page + 1)}
              aria-label={`Turn forward to ${chapters[page + 1]}`}
            >
              <span>turn page</span>
              <ArrowRight />
            </button>
          )}
        </motion.div>

        {opened && (
          <nav className="book-chapters" aria-label="Book chapters">
            {chapters.map((chapter, index) => (
              <button
                key={chapter}
                className={page === index ? "active" : ""}
                onClick={() => turnTo(index)}
                aria-current={page === index ? "page" : undefined}
              >
                <span>{String(index + 1).padStart(2, "0")}</span>
                {chapter}.
              </button>
            ))}
          </nav>
        )}

        {opened && (
          <div className="book-progress" aria-hidden="true">
            <i style={{ width: `${((page + 1) / chapters.length) * 100}%` }} />
          </div>
        )}
      </section>
    </div>
  );
}

function ClosedVolume({ onOpen }: { onOpen: () => void }) {
  return (
    <motion.button
      className="closed-volume"
      type="button"
      onClick={onOpen}
      aria-label="Open the MINERVA volume"
      initial={{ opacity: 0, y: 18, rotateZ: -1.5 }}
      animate={{ opacity: 1, y: 0, rotateZ: -1.5 }}
      exit={{
        opacity: 0,
        x: "-42%",
        rotateY: -118,
        rotateZ: -4,
        transition: { duration: 1.05, ease: [0.3, 0.02, 0.18, 1] },
      }}
      whileHover={{ y: -8, rotateZ: -0.5 }}
      whileTap={{ scale: 0.985 }}
      style={{ transformOrigin: "left center" }}
    >
      <span className="closed-page-block" aria-hidden="true" />
      <span className="leather-cover">
        <span className="cover-spine" aria-hidden="true">
          <i />
          <i />
          <i />
          <i />
        </span>
        <span className="cover-corner corner-tl" aria-hidden="true" />
        <span className="cover-corner corner-tr" aria-hidden="true" />
        <span className="cover-corner corner-bl" aria-hidden="true" />
        <span className="cover-corner corner-br" aria-hidden="true" />
        <span className="cover-border">
          <span className="cover-series">The student research annual · Volume I</span>
          <img className="cover-brand-logo" src="/minerva-logo.png" alt="" />
          <i>empowering ideas today for the breakthroughs of tomorrow</i>
          <span className="cover-invitation">click to open minerva</span>
        </span>
      </span>
      <span className="cover-ribbon" aria-hidden="true" />
      <span className="closed-volume-shadow" aria-hidden="true" />
    </motion.button>
  );
}

function SpreadCrop({ spread, side }: { spread: React.ReactNode; side: "left" | "right" }) {
  return (
    <div className={`spread-crop-content ${side}`}>
      <div className="spread-crop-inner">{spread}</div>
    </div>
  );
}

function OpeningSpread() {
  const [word, setWord] = useState(0);
  const words = ["curious.", "bold.", "written.", "voted."];
  useEffect(() => {
    const timer = window.setInterval(() => setWord((value) => (value + 1) % words.length), 2600);
    return () => window.clearInterval(timer);
  }, []);

  return (
    <>
      <Page side="left" number="01" className="opening-left">
        <div className="inside-cover">
          <img className="inside-cover-logo" src="/minerva-logo.png" alt="MINERVA" />
          <div>
            <span className="eyebrow">minerva · volume one</span>
            <p>
              A year-round science communication and writing initiative organized by the IEEE EMBS
              USJ Chapter.
            </p>
          </div>
        </div>
        <div className="opening-note">
          <span>est. 2025</span>
          <span>colombo · sri lanka</span>
        </div>
      </Page>
      <Page side="right" number="02" className="opening-right">
        <span className="eyebrow">a living collection of student thought.</span>
        <h1>
          wisdom
          <br />
          favours the
          <br />
          <span className="book-word">
            <AnimatePresence mode="wait">
              <motion.em
                key={word}
                initial={{ y: 28, opacity: 0 }}
                animate={{ y: 0, opacity: 1 }}
                exit={{ y: -28, opacity: 0 }}
                transition={{ duration: 0.45 }}
              >
                {words[word]}
              </motion.em>
            </AnimatePresence>
          </span>
        </h1>
        <p>Read ambitious ideas. Meet their authors. Help one voice rise through a public vote.</p>
        <div className="opening-actions">
          <Link className="btn" to="/articles">
            open the library <ArrowRight size={15} />
          </Link>
          <Link to="/about">read our story</Link>
        </div>
        <div className="page-annotation">Begin by turning the page →</div>
      </Page>
    </>
  );
}

function FeaturedSpread({
  articles,
  showVotes,
}: {
  articles: Article[];
  showVotes: boolean;
}) {
  return (
    <>
      <Page side="left" number="03" className="chapter-opener">
        <span className="chapter-number">01</span>
        <span className="eyebrow">featured.</span>
        <h2>Three ideas worth a longer pause.</h2>
        <p>
          Selected by the editorial team for their clarity, courage and capacity to make the
          unfamiliar feel close.
        </p>
        <div className="marginalia">
          <Feather />
          <span>Editor’s selection</span>
        </div>
      </Page>
      <Page side="right" number="04" className="feature-page">
        {articles.map((article, index) => (
          <BookArticle
            key={article.id}
            article={article}
            index={index + 1}
            showVotes={showVotes}
          />
        ))}
        <Link className="book-inline-link" to="/articles">
          see the complete collection <ArrowRight />
        </Link>
      </Page>
    </>
  );
}

function LibrarySpread({ articles }: { articles: Article[] }) {
  return (
    <>
      <Page side="left" number="05" className="library-photo-page">
        <img src={articles[0]?.image} alt="Scientific work represented in the MINERVA collection" />
        <div className="photo-caption">
          <span>Fig. 01</span>
          <p>Ideas become public when language gives them a place to gather.</p>
        </div>
      </Page>
      <Page side="right" number="06" className="library-index-page">
        <span className="eyebrow">the library.</span>
        <h2>Many ways of seeing.</h2>
        <div className="book-index">
          {articles.map((article, index) => (
            <Link to={`/articles/${article.slug || article.id}`} key={article.id}>
              <span>{String(index + 4).padStart(2, "0")}</span>
              <div>
                <h3>{article.title}</h3>
                <small>
                  {article.category} · {article.author}
                </small>
              </div>
              <ArrowRight />
            </Link>
          ))}
        </div>
        <Link className="book-inline-link" to="/articles">
          browse all articles <ArrowRight />
        </Link>
      </Page>
    </>
  );
}

function BallotSpread({ leaders, showVotes }: { leaders: Article[]; showVotes: boolean }) {
  const steps = [
    [BookOpen, "Read", "Every essay is freely available."],
    [UserRound, "Sign in", "Identity protects the public count."],
    [MousePointer2, "Vote", "One permanent vote per article."],
  ] as const;
  return (
    <>
      <Page side="left" number="07" className="ballot-page">
        <span className="eyebrow">how it works.</span>
        <h2>Your curiosity is the ballot.</h2>
        <div className="book-steps">
          {steps.map(([Icon, title, copy], index) => (
            <div key={title}>
              <span>0{index + 1}</span>
              <Icon />
              <p>
                <strong>{title}</strong>
                {copy}
              </p>
            </div>
          ))}
        </div>
        <Link className="btn" to="/articles">
          begin reading
        </Link>
      </Page>
      <Page side="right" number="08" className="ranking-page">
        <div className="ranking-heading">
          <div>
            <span className="eyebrow">live standing.</span>
            <h2>The voices rising.</h2>
          </div>
          <span>{showVotes ? "votes" : "ballot"}</span>
        </div>
        <div className="book-ranking">
          {leaders.map((article, index) => (
            <Link to={`/articles/${article.slug || article.id}`} key={article.id}>
              <strong>{String(index + 1).padStart(2, "0")}</strong>
              <div>
                <h3>{article.title}</h3>
                <small>{article.author}</small>
              </div>
              <span>
                {showVotes ? (
                  <>
                    {index === 0 && <Check />}
                    {article.votes}
                  </>
                ) : (
                  "Sign in"
                )}
              </span>
            </Link>
          ))}
        </div>
        <Link className="book-inline-link" to="/leaderboard">
          open the full standing <ArrowRight />
        </Link>
      </Page>
    </>
  );
}

function StorySpread() {
  return (
    <>
      <Page side="left" number="09" className="story-page">
        <span className="eyebrow">about minerva.</span>
        <h2>A platform built for the courage to explain.</h2>
        <p>
          Named for the goddess of wisdom, MINERVA gives student researchers a place to turn complex
          work into clear public stories.
        </p>
        <Link className="book-inline-link" to="/about">
          discover our story <ArrowRight />
        </Link>
        <blockquote>“Clarity is a form of generosity.”</blockquote>
      </Page>
      <Page side="right" number="10" className="timeline-page">
        <span className="eyebrow">the 2025 chapter.</span>
        <div className="book-timeline">
          {[
            ["June", "First workshop"],
            ["August", "Theme-wise publishing"],
            ["October", "Inter-university Open Category"],
            ["Nov–Dec", "Research & writing workshops"],
            ["December", "Awarding ceremony"],
          ].map(([month, event], index) => (
            <div key={month}>
              <span>{String(index + 1).padStart(2, "0")}</span>
              <strong>{month}</strong>
              <p>{event}</p>
            </div>
          ))}
        </div>
        <div className="book-colophon">
          <img className="colophon-emblem" src="/minerva-logo.png" alt="" />
          <p>IEEE Engineering in Medicine and Biology Society · USJ Student Branch Chapter</p>
        </div>
      </Page>
    </>
  );
}

function Page({
  side,
  number,
  className = "",
  children,
}: {
  side: "left" | "right";
  number: string;
  className?: string;
  children: React.ReactNode;
}) {
  return (
    <article className={`paper-page ${side} ${className}`}>
      <div className="paper-grain" aria-hidden="true" />
      <span className="running-head">
        {side === "left" ? "MINERVA · VOL. I" : "WISDOM IN PUBLIC"}
      </span>
      <div className="paper-content">{children}</div>
      <span className="page-number">{number}</span>
    </article>
  );
}

function BookArticle({
  article,
  index,
  showVotes,
}: {
  article: Article;
  index: number;
  showVotes: boolean;
}) {
  return (
    <Link className="book-article" to={`/articles/${article.slug || article.id}`}>
      <img src={article.image} alt="" />
      <div>
        <span>
          0{index} · {article.category}
        </span>
        <h3>{article.title}</h3>
        <small>
          {article.author} · {showVotes ? `${article.votes} votes` : "Members ballot"}
        </small>
      </div>
      <ArrowRight />
    </Link>
  );
}
