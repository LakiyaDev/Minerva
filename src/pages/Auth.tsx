import { useEffect, useState, type FormEvent } from "react";
import { ArrowLeft, ArrowRight, Check, Eye, EyeOff, ShieldCheck, UserRound } from "lucide-react";
import { Link, useLocation, useNavigate } from "react-router";
import { Brand } from "../components/Brand";
import { GoogleAuthButton } from "../components/GoogleAuthButton";
import { API_BASE_URL } from "../lib/api";
import { useApp } from "../store";

function AuthVisual() {
  return (
    <aside className="auth-visual">
      <Brand light />
      <div className="coin">
        <img className="coin-emblem" src="/minerva-logo.png" alt="" />
      </div>
      <div>
        <span className="eyebrow">a thought from antiquity.</span>
        <blockquote>“The beginning is the most important part of the work.”</blockquote>
        <small>— attributed to Plato</small>
      </div>
    </aside>
  );
}

export function Login() {
  const { setRole, pendingVote } = useApp();
  const navigate = useNavigate();
  const location = useLocation();
  const routeState = location.state as
    | { from?: string; requiredRole?: "participant" | "admin" }
    | undefined;
  const [signInRole, setSignInRole] = useState<"participant" | "admin">(
    routeState?.requiredRole ?? "participant",
  );
  const [show, setShow] = useState(false);
  const [loading, setLoading] = useState(false);
  const submit = (e: FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setTimeout(() => {
      setRole(signInRole);
      const requestedPath = routeState?.from;
      const canOpenRequestedPath =
        requestedPath &&
        (signInRole === "admin"
          ? requestedPath.startsWith("/admin")
          : ["/dashboard", "/my-votes", "/notifications", "/profile"].some(
              (path) => requestedPath === path,
            ));
      navigate(
        canOpenRequestedPath
          ? requestedPath
          : signInRole === "admin"
            ? "/admin"
            : pendingVote
              ? `/articles/${pendingVote}`
              : "/dashboard",
        { replace: true },
      );
    }, 700);
  };
  return (
    <div className="auth-page">
      <AuthVisual />
      <main className="auth-form">
        <Link to="/" className="back">
          <ArrowLeft /> back to MINERVA
        </Link>
        <div>
          <span className="eyebrow">
            {routeState?.requiredRole
              ? `${routeState.requiredRole} access required.`
              : "welcome back."}
          </span>
          <h1>
            {signInRole === "admin" ? "Enter the" : "Continue your"}
            <br />
            <em>{signInRole === "admin" ? "editorial desk." : "reading."}</em>
          </h1>
          <p>
            {signInRole === "admin"
              ? "Sign in with an authorized staff account to manage MINERVA."
              : "Sign in to cast votes and keep track of your ballot."}
          </p>
        </div>
        <div className="role-signin" aria-label="Choose account type">
          <button
            type="button"
            className={signInRole === "participant" ? "active" : ""}
            onClick={() => setSignInRole("participant")}
            aria-pressed={signInRole === "participant"}
          >
            <UserRound />
            <span>
              <strong>Participant</strong>
              <small>Read and vote</small>
            </span>
          </button>
          <button
            type="button"
            className={signInRole === "admin" ? "active" : ""}
            onClick={() => setSignInRole("admin")}
            aria-pressed={signInRole === "admin"}
          >
            <ShieldCheck />
            <span>
              <strong>Editor</strong>
              <small>Manage MINERVA</small>
            </span>
          </button>
        </div>
        {signInRole === "participant" && (
          <>
            <GoogleAuthButton />
            <div className="auth-divider">or sign in with email</div>
          </>
        )}
        <form onSubmit={submit}>
          <label>
            Email address
            <input
              key={signInRole}
              required
              type="email"
              placeholder="name@example.com"
            />
          </label>
          <label>
            Password
            <div className="password">
              <input required type={show ? "text" : "password"} placeholder="Enter password" />
              <button type="button" onClick={() => setShow(!show)} aria-label="Show password">
                {show ? <EyeOff /> : <Eye />}
              </button>
            </div>
          </label>
          <div className="form-row">
            <label className="check">
              <input type="checkbox" /> Remember me
            </label>
            <Link to="/forgot-password">Forgot password?</Link>
          </div>
          <button className="btn full" disabled={loading}>
            {loading ? (
              "opening your desk…"
            ) : (
              <>
                sign in as {signInRole === "admin" ? "editor" : "participant"} <ArrowRight />
              </>
            )}
          </button>
        </form>
        <p className="auth-switch">
          New to MINERVA? <Link to="/register">Create an account</Link>
        </p>
      </main>
    </div>
  );
}

export function Register() {
  const { setRole } = useApp();
  const navigate = useNavigate();
  const [accountRole, setAccountRole] = useState<"participant" | "admin">("participant");
  const [password, setPassword] = useState("");
  const submit = (e: FormEvent) => {
    e.preventDefault();
    setRole(accountRole);
    navigate(accountRole === "admin" ? "/admin" : "/dashboard", { replace: true });
  };
  return (
    <div className="auth-page">
      <AuthVisual />
      <main className="auth-form register-form">
        <Link to="/" className="back">
          <ArrowLeft /> back to MINERVA
        </Link>
        <div>
          <span className="eyebrow">join the conversation.</span>
          <h1>
            {accountRole === "admin" ? "Join the" : "Your voice"}
            <br />
            <em>{accountRole === "admin" ? "editorial team." : "belongs here."}</em>
          </h1>
          <p>
            {accountRole === "admin"
              ? "Staff accounts require an invitation code from the MINERVA committee."
              : "Create an account to read, vote, and follow the articles that matter to you."}
          </p>
        </div>
        <div className="role-signin registration-role" aria-label="Choose account type">
          <button
            type="button"
            className={accountRole === "participant" ? "active" : ""}
            onClick={() => setAccountRole("participant")}
            aria-pressed={accountRole === "participant"}
          >
            <UserRound />
            <span>
              <strong>Participant</strong>
              <small>Public account</small>
            </span>
          </button>
          <button
            type="button"
            className={accountRole === "admin" ? "active" : ""}
            onClick={() => setAccountRole("admin")}
            aria-pressed={accountRole === "admin"}
          >
            <ShieldCheck />
            <span>
              <strong>Editor</strong>
              <small>Staff account</small>
            </span>
          </button>
        </div>
        {accountRole === "participant" && (
          <>
            <GoogleAuthButton />
            <div className="auth-divider">or register with email</div>
          </>
        )}
        <form onSubmit={submit}>
          <label>
            Full name
            <input required placeholder="Your full name" />
          </label>
          <label>
            Email address
            <input required type="email" placeholder="name@university.edu" />
          </label>
          {accountRole === "participant" ? (
            <label>
              University / faculty
              <select required defaultValue="">
                <option value="" disabled>
                  Choose your faculty
                </option>
                <option>Medical Sciences · USJ</option>
                <option>Applied Sciences · USJ</option>
                <option>Technology · USJ</option>
                <option>Humanities · USJ</option>
                <option>Other University · Open Category</option>
              </select>
            </label>
          ) : (
            <>
              <label>
                Editorial responsibility
                <select required defaultValue="">
                  <option value="" disabled>
                    Choose your role
                  </option>
                  <option>Editor</option>
                  <option>Reviewer</option>
                  <option>Voting administrator</option>
                  <option>Chapter administrator</option>
                </select>
              </label>
              <label>
                Staff invitation code
                <input
                  required
                  type="password"
                  autoComplete="one-time-code"
                  placeholder="Enter your committee invitation code"
                />
                <small className="field-note">
                  Invitation codes are issued by the IEEE EMBS USJ committee.
                </small>
              </label>
            </>
          )}
          <label>
            Password
            <input
              required
              minLength={8}
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="At least 8 characters"
            />
            <span className="strength">
              <i style={{ width: `${Math.min(password.length * 10, 100)}%` }} />
            </span>
          </label>
          <label className="check">
            <input required type="checkbox" /> I agree to the voting rules and privacy notice.
          </label>
          <button className="btn full">
            create {accountRole === "admin" ? "editor" : "participant"} account <ArrowRight />
          </button>
        </form>
        <p className="auth-switch">
          Already registered? <Link to="/login">Sign in</Link>
        </p>
      </main>
    </div>
  );
}

export function ForgotPassword() {
  const [sent, setSent] = useState(false);
  return (
    <div className="auth-page">
      <AuthVisual />
      <main className="auth-form">
        <Link to="/login" className="back">
          <ArrowLeft /> return to sign in
        </Link>
        {sent ? (
          <div className="sent-state">
            <span>
              <Check />
            </span>
            <h1>Check your inbox.</h1>
            <p>If an account exists for that address, a reset link is on its way.</p>
            <Link className="btn" to="/login">
              return to sign in
            </Link>
          </div>
        ) : (
          <>
            <div>
              <span className="eyebrow">restore access.</span>
              <h1>
                Forgot your
                <br />
                <em>password?</em>
              </h1>
              <p>Enter your email and we’ll send a secure reset link.</p>
            </div>
            <form
              onSubmit={(e) => {
                e.preventDefault();
                setSent(true);
              }}
            >
              <label>
                Email address
                <input required type="email" placeholder="name@university.edu" />
              </label>
              <button className="btn full">
                send reset link <ArrowRight />
              </button>
            </form>
          </>
        )}
      </main>
    </div>
  );
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}

function isParticipantProfile(payload: unknown): boolean {
  if (!isRecord(payload) || !isRecord(payload.data)) return false;

  const roles = payload.data.roles;
  const capabilities = payload.data.capabilities;
  return (
    (Array.isArray(roles) && roles.includes("participant")) ||
    (isRecord(capabilities) && capabilities.is_participant === true)
  );
}

function googleFailureMessage(reason: string | null): string {
  if (reason === "access_denied") return "Google sign-in was cancelled. You can try again.";
  return "Google sign-in could not be completed. Please try again.";
}

export function GoogleAuthCallback() {
  const { setRole } = useApp();
  const location = useLocation();
  const navigate = useNavigate();
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let active = true;
    const params = new URLSearchParams(location.search);

    if (params.get("auth") !== "success") {
      setError(googleFailureMessage(params.get("reason")));
      setLoading(false);
      return () => {
        active = false;
      };
    }

    const completeSignIn = async () => {
      try {
        const response = await fetch(`${API_BASE_URL}/api/v1/me`, {
          credentials: "include",
          headers: { Accept: "application/json" },
        });
        const payload: unknown = await response.json();

        if (!response.ok || !isParticipantProfile(payload)) {
          throw new Error("The Google session could not be verified.");
        }

        if (active) {
          setRole("participant");
          navigate("/dashboard", { replace: true });
        }
      } catch (error) {
        console.error("Could not complete Google sign-in.", error);
        if (active) {
          setError("We couldn't verify your Google session. Please try again.");
          setLoading(false);
        }
      }
    };

    void completeSignIn();

    return () => {
      active = false;
    };
  }, [location.search, navigate, setRole]);

  return (
    <div className="auth-page">
      <AuthVisual />
      <main className="auth-form">
        <Link to="/login" className="back">
          <ArrowLeft /> back to sign in
        </Link>
        <div className="google-callback-state">
          <span className="eyebrow">participant access.</span>
          <h1>
            {loading ? (
              <>
                Completing your
                <br />
                <em>sign in…</em>
              </>
            ) : (
              <>
                Google sign-in
                <br />
                <em>needs attention.</em>
              </>
            )}
          </h1>
          {error ? (
            <>
              <p role="alert">{error}</p>
              <Link className="btn" to="/login">
                return to sign in
              </Link>
            </>
          ) : (
            <p>Verifying your account securely with MINERVA.</p>
          )}
        </div>
      </main>
    </div>
  );
}
