import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const E2E_DIR = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

/**
 * Load tests/e2e/.env.local (gitignored) into process.env without clobbering
 * already-set variables. Minimal parser — keeps the suite dependency-free.
 */
export function loadE2eEnv(): void {
  const file = path.join(E2E_DIR, '.env.local');
  if (!fs.existsSync(file)) {
    return;
  }

  for (const line of fs.readFileSync(file, 'utf8').split('\n')) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) {
      continue;
    }
    const eq = trimmed.indexOf('=');
    if (eq === -1) {
      continue;
    }
    const key = trimmed.slice(0, eq).trim();
    const value = trimmed.slice(eq + 1).trim().replace(/^["']|["']$/g, '');
    if (key && process.env[key] === undefined) {
      process.env[key] = value;
    }
  }
}

export type Role = 'teller' | 'manager' | 'compliance' | 'admin';

export interface E2eUser {
  username: string;
  password: string;
  /** TOTP secret for pre-enrolled users; fresh installs enroll via the setup page. */
  totp?: string;
}

function roleEnv(role: Role, key: string): string | undefined {
  return process.env[`E2E_${role.toUpperCase()}_${key}`];
}

export const USERS: Record<Role, E2eUser> = {
  teller: {
    username: roleEnv('teller', 'USERNAME') ?? 'teller1',
    password: roleEnv('teller', 'PASSWORD') ?? 'Password123!',
    totp: roleEnv('teller', 'TOTP'),
  },
  manager: {
    username: roleEnv('manager', 'USERNAME') ?? 'manager1',
    password: roleEnv('manager', 'PASSWORD') ?? 'Password123!',
    totp: roleEnv('manager', 'TOTP'),
  },
  compliance: {
    username: roleEnv('compliance', 'USERNAME') ?? 'compliance1',
    password: roleEnv('compliance', 'PASSWORD') ?? 'Password123!',
    totp: roleEnv('compliance', 'TOTP'),
  },
  admin: {
    username: roleEnv('admin', 'USERNAME') ?? 'admin',
    password: roleEnv('admin', 'PASSWORD') ?? 'Password123!',
    totp: roleEnv('admin', 'TOTP'),
  },
};

const AUTH_DIR = path.join(E2E_DIR, '.auth');

export function authFile(role: Role): string {
  return path.join(AUTH_DIR, `${role}.json`);
}

export function secretsFile(): string {
  return path.join(AUTH_DIR, 'secrets.json');
}

/** Read a TOTP secret captured during a previous MFA enrollment. */
export function savedTotpSecret(username: string): string | undefined {
  const file = secretsFile();
  if (!fs.existsSync(file)) {
    return undefined;
  }
  try {
    return JSON.parse(fs.readFileSync(file, 'utf8'))[username];
  } catch {
    return undefined;
  }
}

export function saveTotpSecret(username: string, secret: string): void {
  fs.mkdirSync(AUTH_DIR, { recursive: true });
  const file = secretsFile();
  let data: Record<string, string> = {};
  try {
    data = JSON.parse(fs.readFileSync(file, 'utf8'));
  } catch {
    // Fresh or unreadable file — start over.
  }
  data[username] = secret;
  fs.writeFileSync(file, JSON.stringify(data, null, 2));
}

/** The app's origin — mirrors playwright.config.ts baseURL resolution. */
export function baseUrl(): string {
  return process.env.E2E_BASE_URL ?? process.env.APP_URL ?? 'http://local.host';
}
