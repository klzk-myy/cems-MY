// Shared Playwright helpers for the CEMS-MY browser suites.
//
// These specs are local-only probes (AGENTS.md §6): they drive the real web
// surface at BASE_URL over HTTP only — no direct service calls, no DB access.
//
// Data note: customers and transactions created here are ADDITIVE by design.
// The app exposes no customer/transaction deletion over HTTP (AML retention),
// so every run seeds unique rows via RUN_SEED instead of tearing down.

import { expect, type Page } from '@playwright/test';
import { existsSync, readFileSync, unlinkSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import crypto from 'node:crypto';

// ──────────────────────────────────────────────────────────────
// Environment
// ──────────────────────────────────────────────────────────────

export const BASE_URL = process.env.BASE_URL ?? 'http://local.host';
export const TEST_PASSWORD = process.env.TEST_PASSWORD ?? 'Password123!';

// Only currencies with branch-pool stock and an active rate card can be
// booked. THB has no pool/card; MYR is the base currency.
export const CURRENCIES = ['USD', 'EUR', 'GBP', 'SGD', 'AUD'];

// Teller bookings are limited to 0.5% deviation from the market card, so
// submit the exact card rate for the transaction side (Buy -> rate_buy,
// Sell -> rate_sell).
export const MARKET_RATES: Record<string, { buy: string; sell: string }> = {
  USD: { buy: '4.7200', sell: '4.8100' },
  EUR: { buy: '5.0800', sell: '5.1800' },
  GBP: { buy: '6.0200', sell: '6.1300' },
  SGD: { buy: '3.5200', sell: '3.5800' },
  AUD: { buy: '3.1200', sell: '3.1800' },
};

// Opening floats for the counter session — MYR funds Buy payouts while
// foreign floats seed Sell payouts (Buys replenish foreign during the run).
// MYR is bounded by branch-pool availability.
export const OPENING_FLOATS: Record<string, string> = {
  MYR: '200000', USD: '5000', EUR: '5000', GBP: '3000', SGD: '5000', AUD: '5000',
};

// Per-allocation MYR daily limits — set high so volume runs exercise
// booking logic rather than tripping the cap.
export const DAILY_LIMIT_MYR = '50000000';

// Per-run seed so customer emails/ICs/phones are unique across re-runs —
// id_number_hash and phone_hash are unique blind indexes.
export const RUN_SEED = Math.random().toString(36).slice(2, 10);
export const SEED_NUM = parseInt(RUN_SEED, 36) || 123456789;

// ──────────────────────────────────────────────────────────────
// TOTP (RFC 6238) — mirrors App\Services\System\MfaService:
// HMAC-SHA256, 30-second period, 6 digits, base32-encoded secret.
// ──────────────────────────────────────────────────────────────

const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function base32Decode(secret: string): Buffer {
  const clean = secret.toUpperCase().replace(/[^A-Z2-7]/g, '');
  let bits = '';
  for (const ch of clean) {
    bits += BASE32_ALPHABET.indexOf(ch).toString(2).padStart(5, '0');
  }
  const bytes: number[] = [];
  for (let i = 0; i + 8 <= bits.length; i += 8) {
    bytes.push(parseInt(bits.slice(i, i + 8), 2));
  }
  return Buffer.from(bytes);
}

/** TOTP code for an explicit timestep (30-second windows). */
export function totpAt(secret: string, timestep: number): string {
  const message = Buffer.alloc(8);
  message.writeUInt32BE(Math.floor(timestep / 0x100000000), 0);
  message.writeUInt32BE(timestep % 0x100000000, 4);
  const digest = crypto.createHmac('sha256', base32Decode(secret)).update(message).digest();
  const offset = digest[digest.length - 1] & 0x0f;
  const binary =
    ((digest[offset] & 0x7f) << 24) |
    (digest[offset + 1] << 16) |
    (digest[offset + 2] << 8) |
    digest[offset + 3];
  return String(binary % 1_000_000).padStart(6, '0');
}

export function currentTimestep(): number {
  return Math.floor(Date.now() / 30000);
}

// ──────────────────────────────────────────────────────────────
// MFA state persistence
//
// The TOTP secret is displayed exactly once (at enrollment) and the app
// never re-shows it, so the MFA spec persists it beside this file
// (gitignored) to stay re-runnable. To reset: disable MFA on the test user
// and delete tests/support/.mfa-state.json.
// ──────────────────────────────────────────────────────────────

const MFA_STATE_FILE = join(dirname(fileURLToPath(import.meta.url)), '.mfa-state.json');

export interface MfaState {
  username: string;
  secret: string;
  recoveryCodes: string[];
}

export function loadMfaState(): MfaState | null {
  try {
    if (!existsSync(MFA_STATE_FILE)) {
      return null;
    }
    return JSON.parse(readFileSync(MFA_STATE_FILE, 'utf8')) as MfaState;
  } catch {
    return null;
  }
}

export function saveMfaState(state: MfaState): void {
  writeFileSync(MFA_STATE_FILE, JSON.stringify(state, null, 2));
}

export function clearMfaState(): void {
  try {
    unlinkSync(MFA_STATE_FILE);
  } catch {
    // Already absent — nothing to clean up.
  }
}

// ──────────────────────────────────────────────────────────────
// Nationality distribution for 500 customers
//   200 MY, 100 SG, 80 US, 75 GB, 45 OTHER
// ──────────────────────────────────────────────────────────────
export const NATIONALITY_CYCLE: string[] = [
  'MY','MY','MY','MY',       // 4 MY
  'SG','SG',                  // 2 SG
  'US','US','US','US','US',  // 5 US
  'GB','GB','GB','GB','GB',  // 5 GB
  'MY','MY','MY','MY',       // 4 MY
  'OTHER','OTHER',            // 2 OTHER
  'MY','MY','MY','MY',       // 4 MY
  'SG','SG',                  // 2 SG
  'US','US','US','US','US',  // 5 US
  'GB','GB','GB','GB','GB',  // 5 GB
  'MY','MY','MY','MY',       // 4 MY
  'OTHER','OTHER',            // 2 OTHER
  'MY','MY','MY','MY',       // 4 MY
  'SG','SG',                  // 2 SG
  'US','US','US','US','US',  // 5 US
  'GB','GB','GB','GB','GB',  // 5 GB
  'MY','MY','MY','MY',       // 4 MY
  'OTHER','OTHER',            // 2 OTHER
  'MY','MY','MY','MY',       // 4 MY
  'SG','SG',                  // 2 SG
  'US','US','US','US','US',  // 5 US
  'GB','GB','GB','GB','GB',  // 5 GB
  'MY','MY','MY','MY',       // 4 MY
  'OTHER','OTHER',            // 2 OTHER
  'MY','MY','MY','MY',       // 4 MY
  'SG','SG',                  // 2 SG
  'US','US','US','US','US',  // 5 US
  'GB','GB','GB','GB','GB',  // 5 GB
];

// ──────────────────────────────────────────────────────────────
// Real Malaysian names (diverse: Malay, Chinese, Indian, Iban, Kadazan)
// ──────────────────────────────────────────────────────────────
const malaysianNames = [
  'Tun Abdullah bin Ahmad bin Haji Ibrahim',
  'Datin Sri Dr. Noraini binti Mohamed Ali',
  'Datuk Lee Wei Chen',
  'Rajesh a/l Krishnan',
  'Siti Aminah binti Othman',
  'Goh Boon Teck',
  'Nurul Aisyah bt. Razak',
  'Wong Kam Ming',
  'Priya a/p Subramaniam',
  'Tan Sri Datuk Seri Dr. Hassan bin Hassan',
  'Lim Hui Ling',
  'Mohammad bin Ismail',
  'Chong Wai Yee',
  'Aishah bt. Karim',
  'Ravi a/l Murugan',
  'Stephen Tua Kie Seng',
  'Chew Mei Ling',
  'Abdul Rahman bin Yaacob',
  'Tan Wei Lun',
  'Zainab bt. Hassan',
  'S. Suresh a/l Nair',
  'Datuk Sri Dr. Lim Guan Eng',
  'Sohaila binti Yusof',
  'Phang Yew Keong',
  'Razan bt. Mansor',
  'Kumar a/l Ramasamy',
  'Ho Kah Wei',
  'Fatimah binti Sulaiman',
  'Teh Boon Hock',
  'Anita bt. Ibrahim',
  'Gustav Ngak Aki',
  'Norasikin binti Jantan',
  'Datuk Dr. Ng Kok Song',
  'Aminah bt. Abdullah',
  'Vikneswaran a/l Muthu',
  'Melinda binti Rahman',
  'Dr. Raj Kumar a/l Subramaniam',
  'Siti Noraini bt. Hassan',
  'Teh Chin Siong',
  'Rosnah bt. Ismail',
];

// ──────────────────────────────────────────────────────────────
// Real Singaporean names
// ──────────────────────────────────────────────────────────────
const singaporeanNames = [
  'Tan Kok Wai',
  'Sarah binte Abdullah',
  'Gan Pei Jing',
  'Ravi a/l Suresh',
  'Mei Xing',
  'Muhammad Fikri bin Ahmad',
  'Chew Hui Min',
  'Priya a/p Devi',
  'Ng Jia Hao',
  'Aisyah bt. Razali',
  'Tan Siew Hong',
  'Daniel bin Yusof',
  'Yong Li Ting',
  'Arul a/l Kumar',
  'Rachel Tan',
  'Ibrahim bin Ismail',
  'Goh Siew Lan',
  'Balakumar a/l Subramaniam',
  'Lim Jia En',
  'Zainab bt. Mohamed',
  'Tan Beng Huat',
  'Aisha binte Omar',
  'Koh Wei Lin',
  'Arjun a/l Nair',
  'Wong Mei Hwa',
  'Muhammad Hafiz bin Hassan',
  'Chua Mei Ying',
  'Sanjaya a/l Murthy',
  'Lim Xiu Yun',
  'Noraini bt. Salleh',
];

// ──────────────────────────────────────────────────────────────
// Real American names
// ──────────────────────────────────────────────────────────────
const americanNames = [
  'John Michael Smith',
  'Sarah Elizabeth Johnson',
  'Robert William Davis',
  'Jennifer Anne Wilson',
  'Michael James Brown',
  'Lisa Marie Taylor',
  'David Christopher Anderson',
  'Amanda Rose Thomas',
  'James Edward Jackson',
  'Michelle Lynn White',
  'Christopher Paul Harris',
  'Jessica Marie Martin',
  'Daniel Scott Thompson',
  'Rachel Anne Garcia',
  'Matthew Ryan Martinez',
  'Stephanie Rose Robinson',
  'Andrew James Clark',
  'Nicole Marie Lewis',
  'Kevin Paul Walker',
  'Ashley Marie Hall',
  'William Charles Allen',
  'Kimberly Ann Young',
  'Brian Joseph King',
  'Elizabeth Susan Wright',
  'Richard Allen Lopez',
  'Laura Ann Hill',
  'Thomas Michael Green',
  'Mary Jane Adams',
  'Charles Robert Baker',
  'Karen Marie Nelson',
  'Steven Mark Campbell',
  'Susan Diane Mitchell',
  'Paul Edward Roberts',
  'Nancy Lee Carter',
  'Mark Anthony Phillips',
  'Betty Louise Evans',
  'Donald Ray Turner',
  'Helen Marie Torres',
  'George Louis Parker',
  'Dorothy Ann Collins',
];

// ──────────────────────────────────────────────────────────────
// Real British names
// ──────────────────────────────────────────────────────────────
const britishNames = [
  'James Alexander Thompson',
  'Charlotte Elizabeth Clarke',
  'Oliver Henry Robinson',
  'Emily Rose Watson',
  'Harry James Hughes',
  'Sophie Grace Mitchell',
  'George William Turner',
  'Amelia Jane Phillips',
  'Jack Edward Evans',
  'Isabella Mary Collins',
  'William Thomas Edwards',
  'Olivia Louise Stewart',
  'Henry Charles Morris',
  'Grace Victoria Murphy',
  'Arthur James Bailey',
  'Florence Margaret Cooper',
  'Theodore James Richardson',
  'Matilda Rose Cox',
  'Frederick John Howard',
  'Alice Eleanor Ward',
  'Edward George Hughes',
  'Victoria Anne Price',
  'Charles Robert Webb',
  'Elizabeth Jane Shaw',
  'Arthur William Baker',
  'Margaret Anne Hill',
  'Reginald John Wood',
  'Dorothy May Stone',
  'George Harold Green',
  'Beatrice Anne Baker',
  'Alfred James Adams',
  'Maud Elizabeth Nelson',
  'Frank Edward Mitchell',
  'Evelyn Rose Roberts',
  'Harold George Carter',
  'Constance May Phillips',
  'Ernest Charles Evans',
  'Winifred Anne Turner',
  'Stanley John Collins',
  'Doris Margaret Stewart',
];

// ──────────────────────────────────────────────────────────────
// Real Chinese names
// ──────────────────────────────────────────────────────────────
const chineseNames = [
  'Wei Zhang', 'Liu Yang', 'Wang Lei', 'Chen Jing', 'Li Na',
  'Zhou Tao', 'Wu Qiang', 'Zheng Xin', 'Sun Ming', 'Ma Yan',
  'Guo Ping', 'Lin Bo', 'He Wei', 'Gao Jun', 'Luo Hong',
  'Zhao Lei', 'Huang Chen', 'Xie Fang', 'Han Mei', 'Yang Li',
  'Zhong Shan', 'Wang Wei', 'Zhang Qiang', 'Li Jing', 'Liu Wei',
];

// ──────────────────────────────────────────────────────────────
// Real Japanese names
// ──────────────────────────────────────────────────────────────
const japaneseNames = [
  'Sato Takeshi', 'Suzuki Emi', 'Takahashi Ken', 'Watanabe Yuki', 'Tanaka Haruto',
  'Kobayashi Mei', 'Yamamoto Riku', 'Nakamura Hina', 'Yoshida Sota', 'Kato Akari',
  'Shimizu Ren', 'Hayashi Yui', 'Sakamoto Daiki', 'Miyazaki Hinata', 'Abe Haruki',
  'Ito Minato', 'Nishimura Yuna', 'Maeda Shun', 'Kobayashi Kaito', 'Saito Asahi',
  'Fujita Riko', 'Yamada Takumi', 'Ikeda Nana', 'Morita Sho', 'Ogawa Miki',
];

// ──────────────────────────────────────────────────────────────
// Real Indian names
// ──────────────────────────────────────────────────────────────
const indianNames = [
  'Arjun Sharma', 'Priya Patel', 'Rohan Gupta', 'Ananya Reddy', 'Vikram Singh',
  'Kavita Nair', 'Aditya Kumar', 'Meera Iyer', 'Rahul Desai', 'Deepika Rao',
  'Amit Joshi', 'Sneha Pillai', 'Karan Malhotra', 'Ritu Kapoor', 'Nikhil Menon',
  'Pooja Saxena', 'Rajesh Verma', 'Anjali Choudhary', 'Suresh Bhat', 'Nandita Das',
  'Sanjay Thakur', 'Rohini Hegde', 'Manish Tiwari', 'Jyoti Bansal', 'Alok Mishra',
];

// ──────────────────────────────────────────────────────────────
// Other nationality names (kept for fallback)
// ──────────────────────────────────────────────────────────────
const otherNames = [
  'Nguyen Van Minh',
  'Chen Wei',
  'Kim Min-Jun',
  'Maria Garcia Lopez',
  'Yuki Tanaka',
  'Ahmad bin Hassan',
  'Sakura Yamamoto',
  'Liam O\'Brien',
  'Anastasia Petrov',
  'Luiz Fernando Silva',
  'Patricia Anne Smith',
  'Jean-Pierre Dubois',
  'Hans Mueller',
  'Yoko Nakamura',
  'Raj Patel',
  'Fatima Al-Rashid',
  'Bjorn Eriksson',
  'Olga Ivanova',
  'Carlos Hernandez',
  'Aisha Mohammed',
  'Takeshi Yamamoto',
  'Anna Kowalski',
  'Mohammed Al-Farsi',
  'Ingrid Lindström',
  'Pierre Moreau',
  'Sophie Anderson',
  'Roberto Rossi',
  'Elena Popescu',
  'Hassan Ali',
  'Mei Lin',
];

/** Get a realistic name for a given nationality and 1-based index. */
export function getRealName(index: number, nationality: string): string {
  switch (nationality) {
    case 'MY':  return malaysianNames[index % malaysianNames.length];
    case 'SG':  return singaporeanNames[index % singaporeanNames.length];
    case 'US':  return americanNames[index % americanNames.length];
    case 'GB':  return britishNames[index % britishNames.length];
    case 'CN':  return chineseNames[index % chineseNames.length];
    case 'JP':  return japaneseNames[index % japaneseNames.length];
    case 'IN':  return indianNames[index % indianNames.length];
    case 'OTHER': return otherNames[index % otherNames.length];
    default:    return malaysianNames[index % malaysianNames.length];
  }
}

// ──────────────────────────────────────────────────────────────
// Real format addresses by nationality
// ──────────────────────────────────────────────────────────────
export function getRealAddress(nationality: string, index: number): string {
  switch (nationality) {
    case 'MY': {
      const streets = [
        'No. 42, Jalan Sultan Ismail, 50250 Kuala Lumpur',
        'Lot 18, Tingkat 3, Jalan Tun Razak, 50400 KL',
        '15, Persiaran KLCC, 50088 Kuala Lumpur',
        '33, Jalan Ampang, 50450 KL',
        'No. 77, Jalan Bukit Bintang, 55100 KL',
        '21, Jalan Imbi, 55100 Kuala Lumpur',
        '99, Jalan Sultan Hishamuddin, 50000 KL',
        'No. 5, Jalan P. Ramlee, 50250 KL',
        '12, Jalan Raja Chulan, 50200 KL',
        '66, Jalan Hang Tuah, 50100 KL',
        'No. 28, Jalan Ampang Park, 50450 KL',
        '44, Jalan Yap Kwan Seng, 50450 KL',
        '11, Jalan Stesen Sentral, 50470 KL',
        '88, Jalan Dutamas, 50480 KL',
        'No. 3, Jalan Kiara, Mont Kiara, 50480 KL',
      ];
      return streets[index % streets.length];
    }
    case 'SG': {
      const streets = [
        '123 Orchard Road, #15-01, Singapore 238893',
        '456 Marina Bay Sands, Bayfront Avenue, Singapore 018956',
        '789 Tanglin Road, Singapore 247885',
        '321 Rochor Road, Singapore 188393',
        '654 Jalan Sultan, Singapore 198980',
        '987 Nicoll Highway, Singapore 498990',
        '147 Clementi Avenue 3, Singapore 120147',
        '258 Thomson Road, Singapore 298122',
        '369 Bukit Timah Road, Singapore 229832',
        '741 East Coast Road, Singapore 428835',
        '852 Serangoon Road, Singapore 328831',
        '963 Balestier Road, Singapore 329649',
        '111 Upper Serangoon Road, Singapore 534682',
        '222 Kallang Road, Singapore 349318',
        '333 Toa Payoh Lorong 1, Singapore 310333',
      ];
      return streets[index % streets.length];
    }
    case 'US': {
      const streets = [
        '1234 Main Street, Apt 5B, New York, NY 10001',
        '5678 Broadway Ave, Suite 200, Los Angeles, CA 90001',
        '9012 Oak Drive, Chicago, IL 60601',
        '3456 Maple Avenue, Houston, TX 77001',
        '7890 Pine Road, Phoenix, AZ 85001',
        '2345 Elm Street, Philadelphia, PA 19101',
        '6789 Cedar Lane, San Antonio, TX 78201',
        '4321 Birch Boulevard, San Diego, CA 92101',
        '8765 Walnut Way, Dallas, TX 75201',
        '1357 Cherry Court, San Jose, CA 95101',
        '2468 Ash Street, Austin, TX 73301',
        '9753 Spruce Drive, Jacksonville, FL 32099',
        '1111 Willow Lane, Fort Worth, TX 76101',
        '2222 Poplar Ave, Columbus, OH 43085',
        '3333 Hickory Road, Charlotte, NC 28201',
      ];
      return streets[index % streets.length];
    }
    case 'GB': {
      const streets = [
        '10 Downing Street, London SW1A 2AA',
        '42 Baker Street, London NW1 6TJ',
        '15 Abbey Road, London NW8 9JL',
        '27 Oxford Street, London W1D 1BS',
        '88 Regent Street, London W1B 5EL',
        '5 High Holborn, London WC1V 6DP',
        '47 Piccadilly, London W1J 0DT',
        '22 Fleet Street, London EC4Y 1AA',
        '11 King William Street, London EC4N 7BP',
        '63 Strand, London WC2R 0NR',
        '19 Montague Street, Edinburgh EH1 1YW',
        '34 Castle Street, Edinburgh EH2 3HT',
        '7 Queen Street, Cardiff CF10 2BU',
        '22 Castle Street, Cardiff CF10 1BQ',
        '5 Chapel Lane, Belfast BT1 5GS',
      ];
      return streets[index % streets.length];
    }
    case 'OTHER': {
      const streets = [
        '15 Le Hong Phong, District 10, Ho Chi Minh City',
        '88 Hanoi Old Quarter, Hang Buom, Hanoi',
        '42 Jalan Gurney, 10450 George Town, Penang',
        '77 Suwon-ro, Yeongtong-gu, Suwon 16676',
        '150 Avenida Paulista, São Paulo 01310-100',
        '33 Rue de Rivoli, 75001 Paris',
        '99 Unter den Linden, 10117 Berlin',
        '21 Via del Corso, 00186 Roma',
        '55 Chuo Street, Chiyoda-ku, Tokyo 100-0005',
        '100 Sukhumvit Road, Wattana, Bangkok 10110',
      ];
      return streets[index % streets.length];
    }
    default: {
      return 'No. 42, Jalan Sultan Ismail, 50250 Kuala Lumpur';
    }
  }
}

// ──────────────────────────────────────────────────────────────
// Get the correct ID type and number for the nationality
// ──────────────────────────────────────────────────────────────
export function getIdTypeAndNumber(nationality: string, index: number): { idType: string, idNumber: string } {
  const SEED = parseInt(RUN_SEED, 36) || 123456789;
  switch (nationality) {
    case 'MY': {
      // MyKad: XXXXXX-XX-XXXX where first 6 = YYMMDD
      const year = 80 + (index % 20); // 1980-1999 — year must stay 2 digits
      const month = 1 + (index % 12);
      const day = 1 + (index % 28);
      const serial = (SEED * 97 + index * 13) % 10000;
      return {
        idType: 'MyKad',
        idNumber: `${String(year).padStart(2,'0')}${String(month).padStart(2,'0')}${String(day).padStart(2,'0')}-${String(10 + (index % 90)).padStart(2,'0')}-${String(serial).padStart(4,'0')}`,
      };
    }
    case 'SG': {
      // Singapore passport: S/T format 8 digits + letter
      const prefix = index % 2 === 0 ? 'S' : 'T';
      const num = String(10000000 + (SEED * 137 + index * 137) % 90000000).padStart(8, '0');
      return { idType: 'Passport', idNumber: `${prefix}${num}${['A','B','C','D','E','F','G','H'][index % 8]}` };
    }
    case 'US': {
      // US passport: 9 digits
      return { idType: 'Passport', idNumber: String(100000000 + (SEED * 911 + index * 179) % 900000000).padStart(9, '0') };
    }
    case 'GB': {
      // UK passport: 9 digits
      return { idType: 'Passport', idNumber: String(100000000 + (SEED * 911 + index * 211) % 900000000).padStart(9, '0') };
    }
    case 'CN': {
      // Chinese ID: 18 digits
      return { idType: 'National ID', idNumber: `${SEED}${String(100000 + index * 1234).padStart(6, '0')}${String(1000 + index * 7).padStart(4, '0')}${String(2000 + index * 3).padStart(4, '0')}` };
    }
    case 'JP': {
      // Japanese Residence Card (10 chars)
      return { idType: 'Residence Card', idNumber: `T${SEED}${String(1000 + index * 137).padStart(4, '0')}` };
    }
    case 'IN': {
      // Indian PAN Card
      return { idType: 'PAN Card', idNumber: `${['A','B','C','D','E','F','G','H','J','K'][index%10]}${['A','B','C','D','E','F','G','H','J','K'][index%10]}${String(SEED).padStart(5, '0')}${String(1000 + index * 17).padStart(4, '0')}` };
    }
    case 'OTHER': {
      // Generic passport-style number
      return { idType: 'Passport', idNumber: `P${String(SEED % 1000).padStart(3, '0')}${String(1000 + index * 7).padStart(6, '0')}` };
    }
    default: {
      return { idType: 'MyKad', idNumber: '900101-01-0001' };
    }
  }
}

// ──────────────────────────────────────────────────────────────
// Date of birth for the customer
// ──────────────────────────────────────────────────────────────
export function getDob(nationality: string, index: number): string {
  const baseYear = { MY: 1990, SG: 1985, US: 1975, GB: 1980, CN: 1990, JP: 1985, IN: 1992, OTHER: 1995 }[nationality] ?? 1990;
  const year = baseYear + (index % 25); // spread over 25 years
  const month = 1 + (index % 12);
  const day = 1 + (index % 28);
  return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

// ──────────────────────────────────────────────────────────────
// Phone: always Malaysian format (validation regex requires it)
// ──────────────────────────────────────────────────────────────
export function phoneFor(index: number): string {
  const operators = ['12', '13', '14', '11', '16', '17', '18', '19', '10'];
  const op = operators[index % operators.length];
  const sub = 1000000 + (SEED_NUM * 104729 + index * 13789) % 9000000;
  return `+60${op}${sub}`;
}

// ──────────────────────────────────────────────────────────────
// Authentication
// ──────────────────────────────────────────────────────────────

export async function loginAs(page: Page, username: string): Promise<void> {
  await page.goto(`${BASE_URL}/login`);
  await expect(page.locator('form[action*="login"]')).toBeVisible({ timeout: 10000 });
  await page.fill('input[name="username"]', username);
  await page.fill('input[name="password"]', TEST_PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForLoadState('domcontentloaded');
  await page.waitForSelector('aside', { timeout: 5000 });
}

export async function logout(page: Page): Promise<void> {
  const btn = page.locator('button:has-text("Logout")');
  if (await btn.isVisible().catch(() => false)) {
    await btn.click();
    await page.waitForURL(/\/login/, { timeout: 15000 }).catch(() => {});
  }
  // The login form must render before the session is considered closed — an
  // authenticated GET /login bounces to /dashboard instead of showing it.
  for (let attempt = 0; attempt < 6; attempt++) {
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
    if (await page.locator('form[action*="login"]').isVisible().catch(() => false)) {
      return;
    }
    await page.waitForTimeout(500);
  }
  throw new Error('Logout failed: the login page never rendered (session still active)');
}

// ──────────────────────────────────────────────────────────────
// First-party API calls riding the web session (Sanctum stateful):
// the browser context's cookies authenticate, X-XSRF-TOKEN satisfies
// CSRF, and Referer marks the request as stateful.
// ──────────────────────────────────────────────────────────────

export interface ApiResponse {
  status: number;
  body: any;
}

export async function apiCall(page: Page, method: string, path: string, data?: object): Promise<ApiResponse> {
  const cookies = await page.context().cookies(BASE_URL);
  const xsrf = cookies.find((c) => c.name === 'XSRF-TOKEN')?.value ?? '';
  const res = await page.context().request.fetch(BASE_URL + path, {
    method,
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Referer': BASE_URL + '/',
      'X-XSRF-TOKEN': decodeURIComponent(xsrf),
    },
    data,
  });
  let body: any = null;
  try { body = await res.json(); } catch { /* non-JSON response */ }
  return { status: res.status(), body };
}

export const apiGet = (page: Page, path: string): Promise<ApiResponse> => apiCall(page, 'GET', path);
export const apiPost = (page: Page, path: string, data: object): Promise<ApiResponse> => apiCall(page, 'POST', path, data);

// ──────────────────────────────────────────────────────────────
// Counter-session provisioning (booking precondition)
//
// Booking requires an active teller allocation; the API opening workflow is
// the only path that creates one (request → manager approve → activate →
// session → till floats). Steps are best-effort: a same-day session already
// open (re-run) or a stale pending request skips ahead instead of failing.
// ──────────────────────────────────────────────────────────────

export interface CounterContext {
  tellerId: number;
  branchId: number;
  counterId: number;
  sessionReady: boolean;
}

/**
 * Ensure the manager's branch pool for a currency has at least `needed`
 * available, funding the shortfall via the manager UI (the documented
 * replenishment action, audit-logged as branch_pool_replenished). The
 * caller must be logged in as a manager (manage_stock).
 */
export async function ensurePoolAvailable(page: Page, currency: string, needed: number): Promise<void> {
  await page.goto(`${BASE_URL}/branch-pools`);
  await page.waitForLoadState('domcontentloaded');

  // Row layout: Branch | Currency | Available | Allocated | Total | Actions
  const row = page.locator('table tbody tr').filter({ hasText: currency }).first();
  if (!(await row.count())) {
    console.log(`   ⚠ no branch-pool row for ${currency} — cannot top up`);
    return;
  }
  const availText = ((await row.locator('td').nth(2).textContent()) ?? '0').trim();
  const available = parseFloat(availText.replace(/,/g, '')) || 0;
  if (available >= needed) {
    return;
  }

  const manageHref = await row.locator('a[href*="/branch-pools/"]').first().getAttribute('href');
  const poolId = manageHref?.match(/\/branch-pools\/(\d+)/)?.[1];
  if (!poolId) {
    return;
  }

  await page.goto(`${BASE_URL}/branch-pools/${poolId}`);
  await page.waitForLoadState('domcontentloaded');
  const fundForm = page.locator('form[action$="/fund"]');
  if (!(await fundForm.count())) {
    return;
  }
  const topUp = (needed - available).toFixed(2);
  await fundForm.locator('input[name="quantity"]').fill(topUp);
  await fundForm.locator('button[type="submit"]').click();
  await page.waitForLoadState('domcontentloaded');
  console.log(`   funded ${currency} branch pool #${poolId} with ${topUp} (had ${available})`);
}

/**
 * Top up the manager's branch pool when past runs have drained it. Funding
 * is the documented manager action for pool replenishment; the suite only
 * tops up the shortfall it needs for OPENING_FLOATS, so this is idempotent.
 * The caller must be logged in as a manager (manage_stock).
 */
async function ensurePoolBalances(page: Page): Promise<void> {
  for (const [currency, neededStr] of Object.entries(OPENING_FLOATS)) {
    await ensurePoolAvailable(page, currency, parseFloat(neededStr) || 0);
  }
}

/**
 * Close abandoned counter sessions via the counter API. An OPEN session
 * blocks both the counter and its teller from any new opening — regardless
 * of the session's date — so scan the last several days per counter for one.
 * Counted floats come from the session's reconciliation report (opening
 * balances when nothing was counted) — any variance is recorded
 * transparently, which is the honest closure of an abandoned session.
 * The caller must be logged in as a user with operate_counters (manager).
 */
export async function closeOpenCounterSessions(page: Page, branchId: number): Promise<void> {
  const counters = await apiGet(page, `/api/v1/branches/${branchId}/counters`);
  const list: Array<{ id: number; code?: string }> = counters.body?.data ?? [];
  const scanDays = 10;

  for (const counter of list) {
    for (let d = 0; d < scanDays; d++) {
      const day = new Date(Date.now() - d * 86400000).toISOString().slice(0, 10);
      const report = await apiGet(page, `/api/v1/eod/reconciliation/${day}/counters/${counter.id}`);
      const body = report.body?.data ?? {};
      if (body?.has_session === false || body?.session?.status !== 'open') {
        continue;
      }
      const breakdown: Array<{ currency_code: string; closing_balance: string | null; opening_balance: string | null }>
        = body?.currency_breakdown ?? [];
      const closingFloats: Record<string, string> = {};
      for (const row of breakdown) {
        closingFloats[row.currency_code] = String(row.closing_balance ?? row.opening_balance ?? '0');
      }
      if (Object.keys(closingFloats).length === 0) {
        break;
      }
      const res = await apiPost(page, `/api/v1/counters/${counter.id}/close`, {
        closing_floats: closingFloats,
        notes: 'Playwright suite: closing an abandoned session (counted = opening floats; variance reflects the uncounted day)',
      });
      console.log(`   closed abandoned session at ${counter.code ?? counter.id} (${day}): ${res.status} ${res.body?.message ?? ''}`);
      break;
    }
  }
}

export async function ensureCounterSession(page: Page): Promise<CounterContext> {
  // Phase A (teller): resolve identity and check for an active allocation.
  await loginAs(page, 'teller1');

  const me = await apiGet(page, '/api/v1/user');
  const tellerId: number = me.body?.data?.id ?? 0;
  const branchId: number = me.body?.data?.branch_id ?? 0;
  const countersRes = await apiGet(page, `/api/v1/branches/${branchId}/counters`);
  const counters: Array<{ id: number; code?: string }> = countersRes.body?.data ?? [];

  // An open counter session leaves the teller's allocations 'active' — if
  // one already exists for today the till floats are provisioned and the
  // whole request→approve cycle can be skipped (same-day re-runs).
  const activeCheck = await apiGet(page, '/api/v1/allocations/my-active?currency_code=MYR');
  const sessionReady = activeCheck.status === 200 && activeCheck.body?.data != null;
  console.log(`   active-allocation check: ${activeCheck.status} ${activeCheck.body?.message ?? ''}`);

  if (sessionReady) {
    console.log('   session already provisioned — skipping open cycle');
    await logout(page);
    return { tellerId, branchId, counterId: counters[0]?.id ?? 0, sessionReady: true };
  }

  if (tellerId === 0 || branchId === 0 || counters.length === 0) {
    await logout(page);
    throw new Error(`cannot resolve teller/branch/counters (teller=${tellerId} branch=${branchId} counters=${counters.length})`);
  }
  await logout(page);

  // Phase B (manager): self-heal staging state left by past runs.
  //   1. a counter session left open with no active teller allocation is
  //      stale — close it so a later day-close can settle, and
  //   2. sessions staying open means allocated floats never return to the
  //      pool — top up the shortfall this run needs.
  //   3. read the day's reconciliation to find counters still fresh today —
  //      till balances allow ONE open/close cycle per counter per day, so a
  //      counter already used today (even closed) cannot be re-opened.
  await loginAs(page, 'manager1');
  const date = new Date().toISOString().slice(0, 10);
  const recon = await apiGet(page, `/api/v1/eod/reconciliation/${date}`);
  const summaries: Array<{ counter_id: number; has_session: boolean }> = recon.body?.data?.counter_summaries ?? [];
  await closeOpenCounterSessions(page, branchId);
  await ensurePoolBalances(page);
  await logout(page);

  const freeCounters = counters.filter((c) => {
    const summary = summaries.find((s) => s.counter_id === c.id);
    return !summary || summary.has_session === false;
  });
  if (freeCounters.length === 0) {
    throw new Error(
      'every counter at the branch already has till balances today (one open/close cycle per counter per day)'
      + ' — run again tomorrow or clear today\'s till_balances manually',
    );
  }

  // Phase C: request the opening on the first fresh counter, falling through
  // to the next when one is unexpectedly unusable. A stale PENDING request
  // from a crashed run makes the request fail — approve-and-open can still
  // succeed on it, so only the approve result decides.
  const dailyLimits = Object.fromEntries(Object.keys(OPENING_FLOATS).map((c) => [c, DAILY_LIMIT_MYR]));
  let openedCounterId = 0;
  let lastError = 'no attempt made';

  for (const counter of freeCounters) {
    await loginAs(page, 'teller1');
    const openReq = await apiPost(page, `/api/v1/counters/${counter.id}/opening-request`, {
      requested_floats: OPENING_FLOATS,
    });
    console.log(`   opening-request (${counter.code ?? counter.id}): ${openReq.status} ${openReq.body?.message ?? ''}`);
    await logout(page);

    await loginAs(page, 'manager1');
    const approveRes = await apiPost(page, `/api/v1/counters/${counter.id}/approve-and-open`, {
      teller_id: tellerId,
      approved_floats: OPENING_FLOATS,
      daily_limits: dailyLimits,
    });
    console.log(`   approve-and-open: ${approveRes.status} ${approveRes.body?.message ?? ''}`);
    await logout(page);

    if (approveRes.status === 200) {
      openedCounterId = counter.id;
      break;
    }
    lastError = `${approveRes.status}: ${JSON.stringify(approveRes.body).slice(0, 200)}`;
  }

  if (openedCounterId === 0) {
    throw new Error(`approve-and-open failed on all ${freeCounters.length} fresh counters — last error: ${lastError}`);
  }

  return { tellerId, branchId, counterId: openedCounterId, sessionReady: false };
}

// ──────────────────────────────────────────────────────────────
// Customer creation (web form)
// ──────────────────────────────────────────────────────────────

export async function createCustomer(
  page: Page,
  name: string,
  email: string,
  idType: string,
  idNumber: string,
  nationality: string,
  phone: string,
  address: string,
  dob: string
): Promise<boolean> {
  await page.goto(`${BASE_URL}/customers/create`);
  await page.waitForLoadState('domcontentloaded');
  await page.waitForSelector('form[action*="customers"]', { timeout: 5000 });

  const form = page.locator('form[action*="customers"]');
  await form.locator('input[name="full_name"]').fill(name);
  await form.locator('input[name="email"]').fill(email);
  await form.locator('select[name="id_type"]').selectOption(idType);
  await form.locator('input[name="id_number"]').fill(idNumber);
  await form.locator('select[name="nationality"]').selectOption(nationality);
  await form.locator('input[name="phone"]').fill(phone);
  await form.locator('textarea[name="address"]').fill(address);
  await form.locator('input[name="date_of_birth"]').fill(dob);
  await form.locator('button[type="submit"]').click();
  await page.waitForLoadState('domcontentloaded');

  try {
    if (page.url().includes('customers/create')) {
      const errEl = page.locator('p.text-danger').first();
      const errText = (await errEl.count()) ? ((await errEl.textContent()) ?? '') : '';
      console.log(`   ⚠ Customer ${name} rejected: ${errText.substring(0, 120)}`);
      return false;
    }
  } catch {
    console.log(`   ⚠ Error creating ${name}`);
    return false;
  }
  return true;
}

// ──────────────────────────────────────────────────────────────
// Transaction booking (web form, inline-registration path)
// ──────────────────────────────────────────────────────────────

export interface CustomerProfile {
  idType: string;
  idNumber: string;
  nationality: string;
  phone: string;
  address: string;
  dob: string;
  email: string;
}

export async function createTransaction(
  page: Page,
  name: string,
  txType: string,
  currency: string,
  purpose: string,
  funds: string,
  wealth: string,
  qty: string,
  rate: string,
  cust: CustomerProfile
): Promise<string | null> {
  try {
    await page.goto(`${BASE_URL}/transactions/create`);
    await page.waitForLoadState('domcontentloaded');
    await page.waitForSelector('form[action*="transactions"]', { timeout: 10000 });
  } catch {
    try {
      await page.goto(`${BASE_URL}/transactions/create`, { waitUntil: 'domcontentloaded', timeout: 10000 });
      await page.waitForSelector('form[action*="transactions"]', { timeout: 10000 });
    } catch {
      console.log('   ⚠ Navigation failed for TX');
      return null;
    }
  }

  const form = page.locator('form[action*="transactions"]');

  // Customer section: customer_id is only set by the Alpine typeahead,
  // so without a selection the submit must carry the full inline
  // registration payload — id_type/id_number/nationality/dob are all
  // required_without:customer_id. resolveForBooking then attaches the
  // already-registered customer by id_number blind index.
  const nameInput = form.locator('input[name="full_name"]');
  try { await nameInput.fill(name); } catch { /* typeahead may hide the field */ }
  await form.locator('select[name="id_type"]').selectOption(cust.idType);
  await form.locator('input[name="id_number"]').fill(cust.idNumber);
  await form.locator('select[name="nationality"]').selectOption(cust.nationality);
  await form.locator('input[name="date_of_birth"]').fill(cust.dob);
  await form.locator('input[name="email"]').fill(cust.email);

  // CDD-tier profile fields (Specific needs address; Standard also needs
  // phone/occupation/employer). On a matched record these gap-fill without
  // detaching the customer link.
  await form.locator('input[name="phone"]').fill(cust.phone);
  await form.locator('textarea[name="address"]').fill(cust.address);
  await form.locator('input[name="occupation"]').fill('Trader');
  await form.locator('input[name="employer_name"]').fill('Acme Sdn Bhd');

  await form.locator('select[name="type"]').selectOption(txType);
  await form.locator('select[name="currency_code"]').selectOption({ value: currency });

  await form.locator('input[name="quantity"]').fill(qty);
  await form.locator('input[name="rate"]').fill(rate);
  await form.locator('select[name="purpose"]').selectOption(purpose);
  await form.locator('input[name="source_of_funds"]').fill(funds);
  await form.locator('input[name="source_of_wealth"]').fill(wealth);

  await form.locator('button[type="submit"]').click();
  await page.waitForLoadState('domcontentloaded');

  // Only a numeric id proves a booking — a validation failure lands back
  // on /transactions/create and a bare \w+ match would swallow 'create'
  // as a fake id.
  const url = page.url();
  const match = url.match(/\/transactions\/(\d+)/);
  if (!match) {
    const errEl = page.locator('ul.list-disc li, [class*="alert"] .text-sm, p.text-danger').first();
    const errText = (await errEl.count()) ? ((await errEl.textContent()) ?? '') : '';
    console.log(`   WARN: TX not booked (${txType} ${qty} ${currency}): ${errText.trim().substring(0, 160)}`);
  }
  return match ? match[1] : null;
}

// ──────────────────────────────────────────────────────────────
// Page-state readers
// ──────────────────────────────────────────────────────────────

/**
 * Read the status badge from the page header (the first badge in the header
 * actions slot next to the page's h1 title) — e.g. "Pending Approval",
 * "Completed", "Requested". Returns '' when the page has no status badge.
 */
export async function readHeaderStatus(page: Page): Promise<string> {
  // The x-page-header root is the nearest ancestor div of the h1 using
  // justify-between; its actions slot holds the status badge.
  const headerRoot = page.locator('h1').first()
    .locator('xpath=ancestor::div[contains(@class, "justify-between")][1]');
  const badge = headerRoot.locator('span.inline-flex').first();
  if (!(await badge.count())) {
    return '';
  }
  return ((await badge.textContent()) ?? '').trim();
}

// ──────────────────────────────────────────────────────────────
// Compliance approval with outcome classification
//
// Every booked transaction must resolve to exactly one outcome:
//  - 'approved'          approve form was present and clicked
//  - 'auto-completed'    already final (below auto-approve threshold)
//  - 'held'              pending with an uncleared compliance hold
//  - 'no-approve-button' none of the above — genuinely unexpected
// ──────────────────────────────────────────────────────────────

export type ApproveOutcome = 'approved' | 'auto-completed' | 'held' | 'no-approve-button';

export async function approveTransaction(page: Page, txId: string): Promise<ApproveOutcome> {
  // Straight to the detail page — the pending list is paginated (25/page),
  // so hunting for the row misses transactions on later pages.
  await page.goto(`${BASE_URL}/transactions/${txId}`);
  await page.waitForLoadState('domcontentloaded');

  // Scope to the approve form — bare "Approve" text matches notification
  // rows that are not clickable controls.
  const approveBtn = page.locator('form[action$="/approve"] button[type="submit"]');
  if (await approveBtn.isVisible().catch(() => false)) {
    await approveBtn.click();
    await page.waitForLoadState('domcontentloaded');
    return 'approved';
  }

  const status = await readHeaderStatus(page);
  if (/completed|approved/i.test(status)) {
    return 'auto-completed';
  }

  const clearHoldBtn = page.locator('form[action$="/clear-hold"] button[type="submit"]');
  if (await clearHoldBtn.isVisible().catch(() => false)) {
    return 'held';
  }

  return 'no-approve-button';
}









