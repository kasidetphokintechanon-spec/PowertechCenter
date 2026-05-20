export interface Assignment {
  company: 'PTA' | 'PT4' | 'PTE';
  department: string;
  email: string;
  is_primary: 0 | 1;
}

export interface Employee {
  id: string;
  name: string;
  name_th?: string;
  position?: string;
  phone?: string;
  image?: string;
  birthdate?: string;
  start_date?: string;
  employment_status: 'active' | 'inactive';
  created_at?: string;
  updated_at?: string;
  assignments: Assignment[];
}

export type Company = 'All' | 'PTA' | 'PT4' | 'PTE';

export type SortMode = 'department' | 'name_asc' | 'name_desc' | 'department_asc';

export const DEPARTMENTS = [
  "Accounting", 
  "HR and GA",
  "IT",
  "Safety & Environment",
  "Production",
  "Production Engineer",
  "Maintenance",
  "Logistics",
  "Quality Management"
] as const;

export type Department = typeof DEPARTMENTS[number];

export const POSITION_RANKS: Record<string, number> = {
  "Managing Director": 1,
  "Executive Director and General Manager For Administration Division": 2,
  "Senior Manager": 7,
  "Head of General Administratoin Division": 5,
  "Head of Operations": 6,
  "Digital Transformation Analyst Manager": 7,
  "Manager": 8,
  "Manager (Acting)": 9,
  "Plan Manager": 10,
  "Assistant Manger": 11,
  "Head of Testing": 20,
  "Senior Supervisor": 20,
  "Supervisor (Lv3)": 21,
  "Supervisor (Lv2)": 22,
  "Supervisor (Lv1)": 23,
  "Senior Officer": 30,
  "Senior Programmer": 31,
  "Engineer(Lv3)": 32,
  "Senior Technician": 33,
  "Senior Springer": 34,
  "Officer": 40,
  "Programmer": 41,
  "Engineer(Lv2)": 42,
  "Engineer(Lv1)": 43,
  "Springer": 44,
  "Staff": 50,
  "Factory and Mainteanace": 51,
};

export const POSITION_ABBREVIATIONS: Record<string, string> = {
  'Managing Director': 'MD',
  'Executive Director and General Manager For Administration Division': 'ED/GM',
  'Senior Manager': 'Sr Mgr',
  'Head of General Administratoin Division': 'Head/GA',
  'Head of Operations': 'Head/OP',
  'Head of Testing': 'Head/Test',
  'Manager': 'Mgr',
  'Manager (Acting)': 'Mgr(Act)',
  'Plan Manager': 'Plan Mgr',
  'Assistant Manger': 'Asst Mgr',
  'Senior Supervisor': 'Sr Sup',
  'Supervisor (Lv3)': 'Sup(3)',
  'Supervisor (Lv2)': 'Sup(2)',
  'Supervisor (Lv1)': 'Sup(1)',
  'Senior Officer': 'Sr Off',
  'Senior Programmer': 'Sr Prog',
  'Engineer(Lv3)': 'Eng(3)',
  'Engineer(Lv2)': 'Eng(2)',
  'Engineer(Lv1)': 'Eng(1)',
  'Senior Technician': 'Sr Tech',
  'Senior Springer': 'Sr Spr',
  'Springer': 'Spr',
  'Officer': 'Off',
  'Programmer': 'Prog',
  'Staff': 'Staff',
  'Factory and Mainteanace': 'F&M',
};
