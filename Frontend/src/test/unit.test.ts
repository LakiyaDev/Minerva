import { describe, expect, it } from "vitest";
import { articleStatusLabels } from "../data";
import { formatAttachmentSize } from "../services/articleService";
import { type MeDto } from "../services/authService";
import { deriveRole } from "../store";

const baseUser = (overrides: Partial<MeDto> = {}): MeDto => ({
  id: "01USER",
  name: "Test User",
  email: "user@example.com",
  email_verified: true,
  is_active: true,
  roles: ["participant"],
  permissions: ["participant.vote", "participant.comment", "participant.view_results"],
  capabilities: {
    is_participant: true,
    is_publisher: false,
    can_publish_directly: false,
  },
  two_factor_enabled: false,
  ...overrides,
});

describe("deriveRole (FE-70)", () => {
  it("returns guest when user is null", () => {
    expect(deriveRole(null)).toBe("guest");
  });

  it("returns participant for standard reader accounts", () => {
    expect(deriveRole(baseUser())).toBe("participant");
  });

  it("returns publisher when user has publisher role or article.create permission", () => {
    expect(
      deriveRole(
        baseUser({
          roles: ["participant", "publisher"],
          capabilities: { is_participant: true, is_publisher: true, can_publish_directly: true },
        }),
      ),
    ).toBe("publisher");

    expect(
      deriveRole(
        baseUser({
          roles: ["participant"],
          permissions: ["article.create"],
        }),
      ),
    ).toBe("publisher");
  });

  it("returns admin when user has administrator role or admin permissions", () => {
    expect(deriveRole(baseUser({ roles: ["administrator"] }))).toBe("admin");
    expect(deriveRole(baseUser({ permissions: ["article.unpublish"] }))).toBe("admin");
    expect(deriveRole(baseUser({ permissions: ["user.manage"] }))).toBe("admin");
    expect(deriveRole(baseUser({ permissions: ["settings.manage"] }))).toBe("admin");
  });
});

describe("articleStatusLabels (FE-70)", () => {
  it("maps only draft, published, and unpublished without review queue states", () => {
    expect(Object.keys(articleStatusLabels)).toEqual(["draft", "published", "unpublished"]);
    expect(articleStatusLabels.draft).toBe("Draft");
    expect(articleStatusLabels.published).toBe("Published");
    expect(articleStatusLabels.unpublished).toBe("Unpublished");
  });
});

describe("formatAttachmentSize (FE-70)", () => {
  it("formats bytes, kilobytes, and megabytes cleanly", () => {
    expect(formatAttachmentSize(512)).toBe("1 KB");
    expect(formatAttachmentSize(2048)).toBe("2 KB");
    expect(formatAttachmentSize(5 * 1024 * 1024)).toBe("5.00 MB");
  });
});
