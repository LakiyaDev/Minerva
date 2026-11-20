import { useEffect, useState, type FormEvent } from "react";
import { ArrowLeft, ArrowRight, Check, Eye, EyeOff, ShieldCheck, UserRound } from "lucide-react";
import { Link, useLocation, useNavigate, useSearchParams } from "react-router";
import { Brand } from "../components/Brand";
import { deriveRole, useApp } from "../store";
import {
  acceptStaffInvitation,
  beginStaffMfaSetup,
  confirmStaffMfaSetup,
  forgotStaffPassword,
  getGoogleRedirectUrl,
  resetStaffPassword,
  staffLogin,
  verifyStaffMfa,
  type MeDto,
  type StaffMfaSetupDto,
} from "../services/authService";
import { ApiError, ApiUnavailableError } from "../services/apiClient";

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

function authErrorMessage(error: unknown): string {
  if (error instanceof ApiUnavailableError) {
    return "The MINERVA API is unreachable. Ensure the backend server is running.";
  }

  if (error instanceof ApiError) {
    return error.message;
  }

  if (error instanceof Error) {
    return error.message;
  }

  return "Authentication failed. Please try again.";
}

function defaultHomeFor(user: MeDto): string {
  const resolved = deriveRole(user);
  if (resolved === "admin") return "/admin";
  if (resolved === "publisher") return "/publisher";
  return "/dashboard";
}

function canOpenPath(path: string | undefined, user: MeDto): boolean {
  if (!path) return false;
  const resolved = deriveRole(user);
  if (path.startsWith("/admin")) return resolved === "admin";
  if (path.startsWith("/publisher")) {
    return (
      resolved === "admin" ||
      resolved === "publisher" ||
      user.capabilities.is_publisher ||
      user.permissions.includes("article.create")
    );
  }
  return ["/dashboard", "/my-votes", "/notifications", "/profile"].some(
    (allowed) => path === allowed || path.startsWith("/articles/"),
  );
}

export function Login() {
  const { setAuthenticatedUser, pendingVote } = useApp();
  const navigate = useNavigate();
  const location = useLocation();
  const routeState = location.state as
    | { from?: string; requiredRole?: "participant" | "publisher" | "admin" }
    | undefined;

  const initialTab: "participant" | "staff" =
    routeState?.requiredRole === "admin" || routeState?.requiredRole === "publisher"
      ? "staff"
      : "participant";

  const [tab, setTab] = useState<"participant" | "staff">(initialTab);
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [show, setShow] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [stage, setStage] = useState<"credentials" | "mfa_verify" | "mfa_setup" | "recovery">(
    "credentials",
  );
  const [challengeToken, setChallengeToken] = useState("");
  const [totpCode, setTotpCode] = useState("");
  const [mfaSetup, setMfaSetup] = useState<StaffMfaSetupDto | null>(null);
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
  const [authenticatedUser, setLocalAuthenticatedUser] = useState<MeDto | null>(null);

  const finishLogin = (user: MeDto) => {
    setAuthenticatedUser(user);
    const requested = routeState?.from;
    const target = canOpenPath(requested, user)
      ? requested!
      : pendingVote
        ? `/articles/${pendingVote}`
        : defaultHomeFor(user);
    navigate(target, { replace: true });
  };

  const handleGoogleSignIn = async () => {
    setLoading(true);
    setError(null);
    try {
      const { authorization_url } = await getGoogleRedirectUrl();
      window.location.assign(authorization_url);
    } catch (err) {
      setError(authErrorMessage(err));
      setLoading(false);
    }
  };

  const handleStaffCredentials = async (e: FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);
    try {
      const result = await staffLogin(email.trim(), password);
      if (result.status === "authenticated") {
        finishLogin(result.user);
        return;
      }

      setChallengeToken(result.challenge_token);
      setTotpCode("");

      if (result.status === "mfa_setup_required") {
        const setup = await beginStaffMfaSetup(result.challenge_token);
        setMfaSetup(setup);
        setStage("mfa_setup");
      } else {
        setStage("mfa_verify");
      }
    } catch (err) {
      setError(authErrorMessage(err));
    } finally {
      setLoading(false);
    }
  };

  const handleMfaVerify = async (e: FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);
    try {
      const result = await verifyStaffMfa(challengeToken, totpCode.trim());
      finishLogin(result.user);
    } catch (err) {
      setError(authErrorMessage(err));
    } finally {
      setLoading(false);
    }
  };

  const handleMfaSetupConfirm = async (e: FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);
    try {
      const result = await confirmStaffMfaSetup(challengeToken, totpCode.trim());
      setRecoveryCodes(result.recovery_codes);
      setLocalAuthenticatedUser(result.user);
      setAuthenticatedUser(result.user);
      setStage("recovery");
    } catch (err) {
      setError(authErrorMessage(err));
    } finally {
      setLoading(false);
    }
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
            {tab === "staff" ? "Enter the" : "Continue your"}
            <br />
            <em>{tab === "staff" ? "editorial desk." : "reading."}</em>
          </h1>
          <p>
            {tab === "staff"
              ? "Sign in with your invited staff credentials and authenticator code."
              : "Participants sign in with Google to cast votes and track their ballot."}
          </p>
        </div>

        <div className="role-signin" aria-label="Choose account type">
          <button
            type="button"
            className={tab === "participant" ? "active" : ""}
            onClick={() => {
              setTab("participant");
              setError(null);
            }}
            aria-pressed={tab === "participant"}
          >
            <UserRound />
            <span>
              <strong>Participant</strong>
              <small>Google sign-in</small>
            </span>
          </button>
          <button
            type="button"
            className={tab === "staff" ? "active" : ""}
            onClick={() => {
              setTab("staff");
              setError(null);
            }}
            aria-pressed={tab === "staff"}
          >
            <ShieldCheck />
            <span>
              <strong>Publisher / Editor</strong>
              <small>Password + TOTP</small>
            </span>
          </button>
        </div>

        {error && (
          <p className="field-error" role="alert">
            {error}
          </p>
        )}

        {tab === "participant" ? (
          <div>
            <button
              type="button"
              className="btn full"
              disabled={loading}
              onClick={() => void handleGoogleSignIn()}
            >
              {loading ? "connecting to Google…" : "continue with Google"} <ArrowRight />
            </button>
            <p className="auth-switch">
              New to MINERVA? Signing in with Google provisions your participant account
              automatically.
            </p>
          </div>
        ) : stage === "credentials" ? (
          <form onSubmit={(e) => void handleStaffCredentials(e)}>
            <label>
              Email address
              <input
                required
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="name@example.com"
              />
            </label>
            <label>
              Password
              <div className="password">
                <input
                  required
                  type={show ? "text" : "password"}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="Enter password"
                />
                <button
                  type="button"
                  onClick={() => setShow(!show)}
                  aria-label="Show password"
                >
                  {show ? <EyeOff /> : <Eye />}
                </button>
              </div>
            </label>
            <div className="form-row">
              <span />
              <Link to="/forgot-password">Forgot password?</Link>
            </div>
            <button className="btn full" disabled={loading}>
              {loading ? (
                "verifying credentials…"
              ) : (
                <>
                  sign in as staff <ArrowRight />
                </>
              )}
            </button>
          </form>
        ) : stage === "mfa_verify" ? (
          <form onSubmit={(e) => void handleMfaVerify(e)}>
            <label>
              Authenticator or recovery code
              <input
                required
                value={totpCode}
                onChange={(e) => setTotpCode(e.target.value)}
                placeholder="6-digit TOTP code"
                autoComplete="one-time-code"
              />
            </label>
            <button className="btn full" disabled={loading}>
              {loading ? "verifying code…" : "verify authenticator code"} <ArrowRight />
            </button>
            <button
              type="button"
              className="btn outline full"
              onClick={() => {
                setStage("credentials");
                setError(null);
              }}
            >
              back to password
            </button>
          </form>
        ) : stage === "mfa_setup" ? (
          <form onSubmit={(e) => void handleMfaSetupConfirm(e)}>
            <p>
              Enrol two-factor authentication by adding this setup key in your authenticator app:
            </p>
            <label>
              Secret key
              <input readOnly value={mfaSetup?.secret ?? ""} />
            </label>
            <label>
              Provisioning URI
              <input readOnly value={mfaSetup?.otpauth_url ?? ""} />
            </label>
            <label>
              Confirmation code
              <input
                required
                value={totpCode}
                onChange={(e) => setTotpCode(e.target.value)}
                placeholder="Enter 6-digit code from app"
                autoComplete="one-time-code"
              />
            </label>
            <button className="btn full" disabled={loading}>
              {loading ? "confirming setup…" : "enable two-factor auth"} <ArrowRight />
            </button>
          </form>
        ) : (
          <div>
            <h2>Save your recovery codes.</h2>
            <p>Each code can only be used once if you lose access to your authenticator app.</p>
            <ul>
              {recoveryCodes.map((code) => (
                <li key={code}>
                  <code>{code}</code>
                </li>
              ))}
            </ul>
            <button
              type="button"
              className="btn full"
              onClick={() => {
                if (authenticatedUser) finishLogin(authenticatedUser);
                else navigate("/dashboard", { replace: true });
              }}
            >
              continue to workspace <ArrowRight />
            </button>
          </div>
        )}

        <p className="auth-switch">
          Have a staff invitation? <Link to="/register">Accept invitation</Link>
        </p>
      </main>
    </div>
  );
}

export function AuthCallback() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const { refreshMe, pendingVote } = useApp();
  const [statusMessage, setStatusMessage] = useState("Completing your MINERVA session…");
  const [failed, setFailed] = useState(false);

  const authStatus = searchParams.get("auth");
  const reason = searchParams.get("reason");

  useEffect(() => {
    if (authStatus !== "success") {
      setFailed(true);
      setStatusMessage(
        reason
          ? `Sign-in could not be completed (${reason.replace(/_/g, " ")}).`
          : "Sign-in could not be completed.",
      );
      return;
    }

    let cancelled = false;

    void refreshMe().then((me) => {
      if (cancelled) return;
      if (!me) {
        setFailed(true);
        setStatusMessage("Session cookie was not established. Please try signing in again.");
        return;
      }

      const target = pendingVote ? `/articles/${pendingVote}` : defaultHomeFor(me);
      navigate(target, { replace: true });
    });

    return () => {
      cancelled = true;
    };
  }, [authStatus, reason, refreshMe, navigate, pendingVote]);

  return (
    <div className="auth-page">
      <AuthVisual />
      <main className="auth-form">
        <Link to="/" className="back">
          <ArrowLeft /> back to MINERVA
        </Link>
        <div>
          <span className="eyebrow">google sign-in.</span>
          <h1>{failed ? "Sign-in paused." : "Signing you in."}</h1>
          <p>{statusMessage}</p>
        </div>
        {failed && (
          <Link className="btn" to="/login">
            return to sign in <ArrowRight />
          </Link>
        )}
      </main>
    </div>
  );
}

export function Register() {
  const { setAuthenticatedUser } = useApp();
  const navigate = useNavigate();
  const [accountRole, setAccountRole] = useState<"participant" | "staff">("participant");
  const [name, setName] = useState("");
  const [token, setToken] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

  const handleParticipantGoogle = async () => {
    setLoading(true);
    setError(null);
    try {
      const { authorization_url } = await getGoogleRedirectUrl();
      window.location.assign(authorization_url);
    } catch (err) {
      setError(authErrorMessage(err));
      setLoading(false);
    }
  };

  const handleStaffInvitation = async (e: FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);
    setFieldErrors({});
    try {
      const result = await acceptStaffInvitation({
        token: token.trim(),
        name: name.trim(),
        password,
        password_confirmation: passwordConfirmation,
      });
      setAuthenticatedUser(result.user);
      navigate(defaultHomeFor(result.user), { replace: true });
    } catch (err) {
      if (err instanceof ApiError) {
        setFieldErrors(err.errors);
      }
      setError(authErrorMessage(err));
    } finally {
      setLoading(false);
    }
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
            {accountRole === "staff" ? "Accept your" : "Your voice"}
            <br />
            <em>{accountRole === "staff" ? "invitation." : "belongs here."}</em>
          </h1>
          <p>
            {accountRole === "staff"
              ? "Staff and publisher accounts are provisioned via administrator invitation tokens."
              : "Participant accounts use verified Google Sign-In so every ballot remains trustworthy."}
          </p>
        </div>
        <div className="role-signin registration-role" aria-label="Choose account type">
          <button
            type="button"
            className={accountRole === "participant" ? "active" : ""}
            onClick={() => {
              setAccountRole("participant");
              setError(null);
            }}
            aria-pressed={accountRole === "participant"}
          >
            <UserRound />
            <span>
              <strong>Participant</strong>
              <small>Google account</small>
            </span>
          </button>
          <button
            type="button"
            className={accountRole === "staff" ? "active" : ""}
            onClick={() => {
              setAccountRole("staff");
              setError(null);
            }}
            aria-pressed={accountRole === "staff"}
          >
            <ShieldCheck />
            <span>
              <strong>Publisher / Editor</strong>
              <small>Staff invitation</small>
            </span>
          </button>
        </div>

        {error && (
          <p className="field-error" role="alert">
            {error}
          </p>
        )}

        {accountRole === "participant" ? (
          <div>
            <button
              type="button"
              className="btn full"
              disabled={loading}
              onClick={() => void handleParticipantGoogle()}
            >
              {loading ? "connecting to Google…" : "register with Google"} <ArrowRight />
            </button>
          </div>
        ) : (
          <form onSubmit={(e) => void handleStaffInvitation(e)}>
            <label>
              Staff invitation token
              <input
                required
                type="password"
                autoComplete="one-time-code"
                value={token}
                onChange={(e) => setToken(e.target.value)}
                placeholder="Paste invitation token"
              />
              {fieldErrors.token && <small className="field-error">{fieldErrors.token[0]}</small>}
            </label>
            <label>
              Full name
              <input
                required
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Your full name"
              />
              {fieldErrors.name && <small className="field-error">{fieldErrors.name[0]}</small>}
            </label>
            <label>
              Password
              <input
                required
                minLength={12}
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="At least 12 characters (mixed case, numbers, symbols)"
              />
              <span className="strength">
                <i style={{ width: `${Math.min(password.length * 8, 100)}%` }} />
              </span>
              {fieldErrors.password && (
                <small className="field-error">{fieldErrors.password[0]}</small>
              )}
            </label>
            <label>
              Confirm password
              <input
                required
                minLength={12}
                type="password"
                value={passwordConfirmation}
                onChange={(e) => setPasswordConfirmation(e.target.value)}
                placeholder="Re-enter password"
              />
            </label>
            <button className="btn full" disabled={loading}>
              {loading ? "activating account…" : "accept invitation"} <ArrowRight />
            </button>
          </form>
        )}
        <p className="auth-switch">
          Already registered? <Link to="/login">Sign in</Link>
        </p>
      </main>
    </div>
  );
}

export function ForgotPassword() {
  const [email, setEmail] = useState("");
  const [sent, setSent] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);
    try {
      await forgotStaffPassword(email.trim());
      setSent(true);
    } catch (err) {
      setError(authErrorMessage(err));
    } finally {
      setLoading(false);
    }
  };

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
            <p>If a staff account exists for that address, a reset link is on its way.</p>
            <Link className="btn" to="/login">
              return to sign in
            </Link>
          </div>
        ) : (
          <>
            <div>
              <span className="eyebrow">restore staff access.</span>
              <h1>
                Forgot your
                <br />
                <em>password?</em>
              </h1>
              <p>Enter your staff email and we’ll send a secure reset link.</p>
            </div>
            {error && (
              <p className="field-error" role="alert">
                {error}
              </p>
            )}
            <form onSubmit={(e) => void handleSubmit(e)}>
              <label>
                Email address
                <input
                  required
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="name@university.edu"
                />
              </label>
              <button className="btn full" disabled={loading}>
                {loading ? "sending…" : "send reset link"} <ArrowRight />
              </button>
            </form>
          </>
        )}
      </main>
    </div>
  );
}

export function ResetPassword() {
  const [searchParams] = useSearchParams();
  const initialToken = searchParams.get("token") ?? "";
  const initialEmail = searchParams.get("email") ?? "";

  const [token, setToken] = useState(initialToken);
  const [email, setEmail] = useState(initialEmail);
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [done, setDone] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);
    setFieldErrors({});

    try {
      await resetStaffPassword({
        token: token.trim(),
        email: email.trim(),
        password,
        password_confirmation: passwordConfirmation,
      });
      setDone(true);
    } catch (err) {
      if (err instanceof ApiError) {
        setFieldErrors(err.errors);
        setError(err.message);
      } else {
        setError(authErrorMessage(err));
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="auth-page">
      <AuthVisual />
      <main className="auth-form">
        <Link to="/login" className="back">
          <ArrowLeft /> return to sign in
        </Link>
        {done ? (
          <div className="sent-state">
            <span>
              <Check />
            </span>
            <h1>Password updated.</h1>
            <p>Your staff password has been reset and previous sessions were signed out.</p>
            <Link className="btn" to="/login">
              sign in with new password <ArrowRight />
            </Link>
          </div>
        ) : (
          <>
            <div>
              <span className="eyebrow">restore staff access.</span>
              <h1>
                Choose a new
                <br />
                <em>password.</em>
              </h1>
              <p>Enter a strong new password for your Minerva staff account.</p>
            </div>
            {error && (
              <p className="field-error" role="alert">
                {error}
              </p>
            )}
            <form onSubmit={(e) => void handleSubmit(e)}>
              {!initialToken && (
                <label>
                  Reset token
                  <input
                    required
                    value={token}
                    onChange={(e) => setToken(e.target.value)}
                    placeholder="Paste reset token"
                  />
                  {fieldErrors.token && (
                    <small className="field-error">{fieldErrors.token[0]}</small>
                  )}
                </label>
              )}
              <label>
                Email address
                <input
                  required
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="name@university.edu"
                  readOnly={Boolean(initialEmail)}
                />
                {fieldErrors.email && (
                  <small className="field-error">{fieldErrors.email[0]}</small>
                )}
              </label>
              <label>
                New password
                <div className="pw-input">
                  <input
                    required
                    type={showPassword ? "text" : "password"}
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    placeholder="Minimum 12 characters"
                    minLength={12}
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword((s) => !s)}
                    aria-label={showPassword ? "Hide password" : "Show password"}
                  >
                    {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                  </button>
                </div>
                {fieldErrors.password && (
                  <small className="field-error">{fieldErrors.password[0]}</small>
                )}
              </label>
              <label>
                Confirm new password
                <input
                  required
                  type={showPassword ? "text" : "password"}
                  value={passwordConfirmation}
                  onChange={(e) => setPasswordConfirmation(e.target.value)}
                  placeholder="Repeat new password"
                  minLength={12}
                />
              </label>
              <button className="btn full" disabled={loading}>
                {loading ? "resetting…" : "reset password"} <ArrowRight />
              </button>
            </form>
          </>
        )}
      </main>
    </div>
  );
}
