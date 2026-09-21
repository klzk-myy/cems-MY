import { test, expect } from '@playwright/test';

const BASE_URL = 'http://local.host';

// Only currencies with branch-pool stock and an active rate card can be
// booked. THB has no pool/card; MYR is the base currency.
const CURRENCIES = ['USD', 'EUR', 'GBP', 'SGD', 'AUD'];

// Teller bookings are limited to 0.5% deviation from the market card, so
// submit the exact card rate for the transaction side (Buy -> rate_buy,
// Sell -> rate_sell).
const MARKET_RATES: Record<string, { buy: string; sell: string }> = {
  USD: { buy: '4.7200', sell: '4.8100' },
  EUR: { buy: '5.0800', sell: '5.1800' },
  GBP: { buy: '6.0200', sell: '6.1300' },
  SGD: { buy: '3.5200', sell: '3.5800' },
  AUD: { buy: '3.1200', sell: '3.1800' },
};

// Opening floats for the counter session — MYR funds Buy payouts while
// foreign floats seed Sell payouts (Buys replenish foreign during the run).
// MYR is bounded by branch-pool availability.
const OPENING_FLOATS: Record<string, string> = {
  MYR: '200000', USD: '5000', EUR: '5000', GBP: '3000', SGD: '5000', AUD: '5000',
};

// Per-allocation MYR daily limits — set high so the volume run exercises
// booking logic rather than tripping the cap.
const DAILY_LIMIT_MYR = '50000000';

// Per-run seed so customer emails/ICs/phones are unique across re-runs —
// id_number_hash and phone_hash are unique blind indexes.
const RUN_SEED = Math.random().toString(36).slice(2, 10);
const SEED_NUM = parseInt(RUN_SEED, 36) || 123456789;

// ──────────────────────────────────────────────────────────────
// Nationality distribution for 500 customers
//   200 MY, 100 SG, 80 US, 75 GB, 45 OTHER
// ──────────────────────────────────────────────────────────────
const NATIONALITY_CYCLE: string[] = [
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

// ──────────────────────────────────────────────────────────────
// Get a real name for a given nationality index
// ──────────────────────────────────────────────────────────────
function getRealName(index: number, nationality: string): string {
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
function getRealAddress(nationality: string, index: number): string {
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
        '852 Jurong East Street 11, Singapore 609604',
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
function getIdTypeAndNumber(nationality: string, index: number): { idType: string, idNumber: string } {
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
function getDob(nationality: string, index: number): string {
  const baseYear = { MY: 1990, SG: 1985, US: 1975, GB: 1980, CN: 1990, JP: 1985, IN: 1992, OTHER: 1995 }[nationality] ?? 1990;
  const year = baseYear + (index % 25); // spread over 25 years
  const month = 1 + (index % 12);
  const day = 1 + (index % 28);
  return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

// ──────────────────────────────────────────────────────────────
// Phone: always Malaysian format (validation regex requires it)
// ──────────────────────────────────────────────────────────────
function phoneFor(index: number): string {
  const operators = ['12', '13', '14', '11', '16', '17', '18', '19', '10'];
  const op = operators[index % operators.length];
  const sub = 1000000 + (SEED_NUM * 104729 + index * 13789) % 9000000;
  return `+60${op}${sub}`;
}

test.describe('500 Transaction Flow: Teller → Manager → Compliance', () => {

  async function logout(page: any) {
    await page.click('button:has-text("Logout")');
    await page.waitForTimeout(1000); /* wait for logout redirect */ console.log("Logged out");
  }

  async function loginAs(page: any, username: string) {
    await page.goto(BASE_URL + '/login');
    await expect(page.locator('form[action*="login"]')).toBeVisible({ timeout: 10000 });
    await page.fill('input[name="username"]', username);
    await page.fill('input[name="password"]', 'Password123!');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForSelector('aside', { timeout: 5000 });
  }

  const loginAsTeller = (page: any) => loginAs(page, 'teller1');
  const loginAsManager = (page: any) => loginAs(page, 'manager1');
  const loginAsCompliance = (page: any) => loginAs(page, 'compliance1');

  // First-party API calls ride the web session (Sanctum stateful): the
  // browser context's cookies authenticate, X-XSRF-TOKEN satisfies CSRF,
  // and Referer marks the request as stateful.
  async function apiCall(page: any, method: string, path: string, data?: object): Promise<{ status: number; body: any }> {
    const cookies = await page.context().cookies(BASE_URL);
    const xsrf = cookies.find((c: any) => c.name === 'XSRF-TOKEN')?.value ?? '';
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
    try { body = await res.json(); } catch {}
    return { status: res.status(), body };
  }

  const apiGet = (page: any, path: string) => apiCall(page, 'GET', path);
  const apiPost = (page: any, path: string, data: object) => apiCall(page, 'POST', path, data);

  async function createCustomer(
    page: any,
    name: string,
    email: string,
    idType: string,
    idNumber: string,
    nationality: string,
    phone: string,
    address: string,
    dob: string
  ): Promise<boolean> {
    await page.goto(BASE_URL + '/customers/create');
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

  async function createTransaction(
    page: any,
    name: string,
    txType: string,
    currency: string,
    purpose: string,
    funds: string,
    wealth: string,
    qty: string,
    rate: string,
    cust: { idType: string; idNumber: string; nationality: string; phone: string; address: string; dob: string; email: string }
  ): Promise<string | null> {
    try {
      await page.goto(BASE_URL + '/transactions/create');
      await page.waitForLoadState('domcontentloaded');
      await page.waitForSelector('form[action*="transactions"]', { timeout: 10000 });
    } catch {
      try {
        await page.goto(BASE_URL + '/transactions/create', { waitUntil: 'domcontentloaded', timeout: 10000 });
        await page.waitForSelector('form[action*="transactions"]', { timeout: 10000 });
      } catch {
        console.log(`   ⚠ Navigation failed for TX`);
        return null;
      }
    }
    await page.waitForSelector('form[action*="transactions"]', { timeout: 10000 });

    const form = page.locator('form[action*="transactions"]');

    // Customer section: customer_id is only set by the Alpine typeahead,
    // so without a selection the submit must carry the full inline
    // registration payload — id_type/id_number/nationality/dob are all
    // required_without:customer_id. resolveForBooking then attaches the
    // already-registered customer by id_number blind index.
    const nameInput = form.locator('input[name="full_name"]');
    try { await nameInput.fill(name); } catch {}
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

  async function approveTransaction(page: any, txId: string): Promise<string> {
    // Straight to the detail page — the pending list is paginated (25/page),
    // so hunting for the row misses transactions on later pages.
    await page.goto(BASE_URL + '/transactions/' + txId);
    await page.waitForLoadState('domcontentloaded');

    // Scope to the approve form — bare "Approve" text matches notification
    // rows that are not clickable controls.
    const approveBtn = page.locator('form[action$="/approve"] button[type="submit"]');
    if (await approveBtn.isVisible().catch(() => false)) {
      await approveBtn.click();
      await page.waitForLoadState('domcontentloaded');
      return 'approved';
    }

    return 'skipped';
  }

  test('process 500 transactions across 5 nationalities', async ({ page }) => {
    const startTime = Date.now();
    test.setTimeout(10800000);
    page.setDefaultNavigationTimeout(30000);
    page.setDefaultTimeout(30000);

    // ──────────────────────────────────────────────────────────────
    // PHASE 1: TELLER — Counter opening request (allocation + session)
    //
    // Booking requires an open counter session with today's till_balances;
    // the API opening workflow is the only path that creates them
    // (request → manager approve → activate → session → till floats).
    // Steps are best-effort: a same-day session already open (re-run) or a
    // drained pool skips ahead to the existing session instead of failing.
    // ──────────────────────────────────────────────────────────────
    console.log('━━━ Phase 1: Teller — Counter Opening Request ━━━━━━━━━━━\n');
    await loginAsTeller(page);
    console.log('1. Logged in as teller1\n');

    const me = await apiGet(page, '/api/v1/user');
    const tellerId: number = me.body?.data?.id ?? 0;
    const branchId: number = me.body?.data?.branch_id ?? 0;
    console.log(`   teller id=${tellerId} branch=${branchId}\n`);

    const countersRes = await apiGet(page, `/api/v1/branches/${branchId}/counters`);
    const counterId: number = countersRes.body?.data?.[0]?.id ?? 0;
    console.log(`   counter id=${counterId}\n`);

    // An open counter session leaves the teller's allocations 'active' — if
    // one already exists for today the till floats are provisioned and the
    // whole request→approve cycle can be skipped (same-day re-runs).
    // approve-and-open requires PENDING allocations, so it must only run
    // when the opening-request below actually created them.
    const activeCheck = await apiGet(page, '/api/v1/allocations/my-active?currency_code=MYR');
    const sessionReady = activeCheck.status === 200 && activeCheck.body?.data != null;
    console.log(`2. Active allocation check: ${activeCheck.status} ${activeCheck.body?.message ?? ''}\n`);

    let openingRequested = false;
    if (sessionReady) {
      console.log('   Session already provisioned — skipping open cycle\n');
    } else {
      const openReq = await apiPost(page, `/api/v1/counters/${counterId}/opening-request`, {
        requested_floats: OPENING_FLOATS,
      });
      openingRequested = openReq.status === 200;
      console.log(`3. Opening request: ${openReq.status} ${openReq.body?.message ?? ''}\n`);
    }

    await logout(page);

    // ──────────────────────────────────────────────────────────────
    // PHASE 2: MANAGER — Approve floats and open the counter session
    // ──────────────────────────────────────────────────────────────
    if (openingRequested) {
      console.log('━━━ Phase 2: Manager — Approve & Open Counter ━━━━━━━━━━━\n');
      await loginAsManager(page);
      console.log('4. Logged in as manager1\n');

      const dailyLimits = Object.fromEntries(
        Object.keys(OPENING_FLOATS).map((c) => [c, DAILY_LIMIT_MYR])
      );
      const approveRes = await apiPost(page, `/api/v1/counters/${counterId}/approve-and-open`, {
        teller_id: tellerId,
        approved_floats: OPENING_FLOATS,
        daily_limits: dailyLimits,
      });
      console.log(`5. Approve-and-open: ${approveRes.status} ${approveRes.body?.message ?? ''}\n`);

      await logout(page);
      console.log('6. Logged out\n');
    } else {
      console.log('━━━ Phase 2: Manager — skipped (no pending request) ━━━━━━━━\n');
    }

    // ──────────────────────────────────────────────────────────────
    // PHASE 3: TELLER — Create 500 Customers + Transactions
    // ──────────────────────────────────────────────────────────────
    console.log('━━━ Phase 3: Teller — Create Customers & Transactions ━━━━━━\n');
    await loginAsTeller(page);
    console.log('6. Logged in as teller1\n');

    const purposes = ['Travel', 'Education', 'Medical', 'Business', 'Investment',
      'Family Support', 'Migration', 'Other'];
    const fundSources = ['Salary', 'Savings', 'Business Income', 'Investment Returns',
      'Inheritance', 'Consulting Fees', 'Dividends'];
    const wealthSources = ['Employment', 'Business Ownership', 'Professional Services',
      'Real Estate', 'Financial Investments', 'Government Position', 'Agriculture'];

    // Returning customers (reuse fixed names, run-seeded emails/ICs)
    const returnedCustomers = [
      { name: 'Tun Abdullah bin Ahmad bin Haji Ibrahim', email: `ahmad.${RUN_SEED}@corp.my` },
      { name: 'Datin Sri Dr. Noraini binti Mohamed Ali', email: `noraini.${RUN_SEED}@bank.my` },
      { name: 'Datuk Lee Wei Chen', email: `weichen.${RUN_SEED}@trading.my` },
    ];

    let txCreated = 0;
    const txIds: string[] = [];

    for (let i = 1; i <= 500; i++) {
      // Total setup failure must not burn the whole loop producing warnings —
      // covers both customer-rejection and booking-failure paths.
      if (i > 10 && txCreated === 0) {
        throw new Error('No transactions booked after 10 attempts — counter session or till floats are not provisioned');
      }

      // Reuse customers at indices 1, 101, 201, 301, 401
      const isReturning = [0, 100, 200, 300, 400].includes(i - 1);
      const custIdx = isReturning ? Math.floor((i - 1) / 100) : 0;
      const cust = isReturning ? returnedCustomers[custIdx] : null;

      if (i % 100 === 0) {
        const nationality = NATIONALITY_CYCLE[(i - 1) % NATIONALITY_CYCLE.length];
        console.log(`[TX ${i}/500] ${isReturning ? 'Returning customer' : 'New customer'} — Nationality: ${nationality}`);
      }

      const nationality = NATIONALITY_CYCLE[(i - 1) % NATIONALITY_CYCLE.length];
      const name = cust ? cust.name : getRealName(i, nationality);
      const email = cust ? cust.email : `${RUN_SEED}${i}@test.com`;
      const { idType, idNumber } = getIdTypeAndNumber(nationality, i);
      const address = getRealAddress(nationality, i);
      const dob = getDob(nationality, i);

      const created = await createCustomer(
        page, name, email, idType, idNumber, nationality,
        phoneFor(i), address, dob
      );

      if (!created) {
        if (i % 50 === 0) {
          console.log(`   ⚠ Customer ${i} (${name}) not created`);
        }
        continue;
      }

      const currency = CURRENCIES[i % CURRENCIES.length];
      const purpose = purposes[i % purposes.length];
      const funds = fundSources[i % fundSources.length];
      const wealth = wealthSources[i % wealthSources.length];
      // 1:1 Buy:Sell keeps MYR/foreign till liquidity roughly self-balancing:
      // each Buy pays MYR out (refilled by the next Sell) and each Sell
      // drains foreign (refilled by the previous Buy). Modest quantities
      // keep the run inside the branch pool's capacity.
      const qty = (200 + i).toFixed(2);
      const txType = i % 2 === 0 ? 'Sell' : 'Buy';
      const rate = txType === 'Buy' ? MARKET_RATES[currency].buy : MARKET_RATES[currency].sell;

      const txId = await createTransaction(page, name, txType, currency, purpose, funds, wealth, qty, rate,
        { idType, idNumber, nationality, phone: phoneFor(i), address, dob, email });

      if (txId) {
        txCreated++;
        txIds.push(txId);
      } else {
        if (i % 50 === 0) {
          console.log(`   WARN: TX ${i} (${txType} ${qty} ${currency} @ ${rate}) was not booked`);
        }
      }

    }

    console.log(`\n✅ Created ${txCreated} transactions (${txIds.length} IDs captured)\n`);

    await logout(page);
    console.log('7. Logged out\n');

    // ──────────────────────────────────────────────────────────────
    // PHASE 4: COMPLIANCE — Approve All
    // ──────────────────────────────────────────────────────────────
    console.log('━━━ Phase 4: Compliance — Approve All ━━━━━━━━━━━━━━━━━━━━━━\n');
    await loginAsCompliance(page);
    console.log('8. Logged in as compliance1\n');

    // Check pending count
    await page.goto(BASE_URL + '/transactions?status=pending_approval');
    await page.waitForLoadState('domcontentloaded');
    let pendingCount = await page.locator('table tbody tr').count();
    console.log(`9. Found ${pendingCount} pending transactions\n`);

    // Approve each captured transaction
    let approvedCount = 0;
    let skippedCount = 0;
    for (let i = 0; i < txIds.length; i++) {
      const result = await approveTransaction(page, txIds[i]);
      if (result === 'approved') approvedCount++;
      else if (result === 'skipped') skippedCount++;
      if ((i + 1) % 100 === 0) console.log(`   Processed ${i + 1}/${txIds.length}`);
      if (i < txIds.length - 1) await page.waitForTimeout(300);
    }

    console.log(`\n✅ Approved ${approvedCount}, already-final ${skippedCount}, total ${txIds.length} transactions\n`);

    // ──────────────────────────────────────────────────────────────
    // PHASE 5: Verification
    // ──────────────────────────────────────────────────────────────
    console.log('━━━ Phase 5: Verification ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');
    await page.goto(BASE_URL + '/transactions');
    await page.waitForLoadState('domcontentloaded');

    const totalTxCount = await page.locator('table tbody tr').count();
    console.log(`Total transactions visible on list page: ${totalTxCount}\n`);

    // Spot check some transactions
    const spotChecks = [0, Math.floor(txIds.length / 2), txIds.length - 1];
    for (const idx of spotChecks) {
      if (idx < txIds.length) {
        await page.goto(BASE_URL + '/transactions/' + txIds[idx]);
        await page.waitForLoadState('domcontentloaded');
        const statusEl = page.locator('[class*="status"], [class*="badge"]').first();
        if (await statusEl.count() > 0) {
          console.log(`  TX ${txIds[idx]}: ${await statusEl.textContent()}`);
        }
      }
    }

    const elapsed = ((Date.now() - startTime) / 1000).toFixed(0);
    console.log(`\n========================================`);
    console.log(` DONE! ${txCreated} created, ${approvedCount} approved in ${elapsed}s`);
    console.log(`========================================`);

    // A run that booked nothing is a failure, not a pass — the numeric-id
    // capture means txCreated only counts real persisted transactions.
    expect(txCreated).toBeGreaterThan(0);
    expect(txIds.length).toBe(txCreated);
  });
});
