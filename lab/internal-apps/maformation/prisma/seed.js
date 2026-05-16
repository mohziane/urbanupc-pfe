import '../src/bootstrap.js'; // builds DATABASE_URL from the secret file
import { PrismaClient } from '@prisma/client';
const prisma = new PrismaClient();

const courses = [
  { code: 'M1-CYBER-101', title: 'Fondamentaux Cybersécurité', description: 'CIA, modèles de menace, ANSSI', ects: 6, semester: 'S1' },
  { code: 'M1-CYBER-102', title: 'Cryptographie Appliquée',     description: 'Symétrique, asymétrique, PKI, TLS', ects: 4, semester: 'S1' },
  { code: 'M1-CYBER-103', title: 'Sécurité Réseau',             description: 'Firewall, VPN, IDS/IPS, segmentation', ects: 5, semester: 'S1' },
  { code: 'M1-CYBER-104', title: 'Sécurité Web et OWASP',       description: 'Top 10, audit, durcissement', ects: 5, semester: 'S1' },
  { code: 'M1-CYBER-201', title: 'SOC et Détection',            description: 'SIEM Wazuh, Sysmon, MITRE ATT&CK', ects: 6, semester: 'S2' },
  { code: 'M1-CYBER-202', title: 'Gouvernance et Homologation', description: 'EBIOS RM, RGS, ISO 27001', ects: 4, semester: 'S2' },
];

const slots = [
  { code: 'M1-CYBER-101', day: 1, start: '09:00', end: '12:00', room: 'A201' },
  { code: 'M1-CYBER-102', day: 1, start: '14:00', end: '17:00', room: 'B105' },
  { code: 'M1-CYBER-103', day: 2, start: '09:00', end: '12:00', room: 'A203' },
  { code: 'M1-CYBER-104', day: 3, start: '13:30', end: '17:30', room: 'TP-Lab' },
  { code: 'M1-CYBER-201', day: 4, start: '09:00', end: '12:00', room: 'SOC-Room' },
  { code: 'M1-CYBER-202', day: 5, start: '10:00', end: '12:00', room: 'A105' },
];

async function main() {
  for (const c of courses) {
    await prisma.course.upsert({ where: { code: c.code }, update: c, create: c });
  }
  for (const s of slots) {
    const course = await prisma.course.findUnique({ where: { code: s.code } });
    if (!course) continue;
    await prisma.scheduleSlot.deleteMany({ where: { courseId: course.id } });
    await prisma.scheduleSlot.create({
      data: { courseId: course.id, dayOfWeek: s.day, startTime: s.start, endTime: s.end, room: s.room },
    });
  }
  console.log('Seed: courses + schedule OK. Users will be enrolled at first LDAP login.');
}

main().catch((e) => { console.error(e); process.exit(1); }).finally(() => prisma.$disconnect());
