"use client";

import React from "react"

import { cn } from "@/lib/utils";
import type { Employee } from "@/types/employee";
import { POSITION_ABBREVIATIONS } from "@/types/employee";
import {
  Briefcase,
  Building2,
  Calendar,
  Check,
  Copy,
  Crown,
  Mail,
  Phone,
  Star,
  User,
  X,
} from "lucide-react";
import { useState } from "react";

interface EmployeeDetailProps {
  employee: Employee | null;
  isFavorite?: boolean;
  onToggleFavorite?: () => void;
  onClose?: () => void;
}

export function EmployeeDetail({
  employee,
  isFavorite,
  onToggleFavorite,
  onClose,
}: EmployeeDetailProps) {
  const [copiedField, setCopiedField] = useState<string | null>(null);

  if (!employee) {
    return (
      <div className="h-full flex items-center justify-center text-muted-foreground">
        <div className="text-center">
          <User className="w-16 h-16 mx-auto mb-4 opacity-20" />
          <p className="text-sm">เลือกพนักงานเพื่อดูรายละเอียด</p>
        </div>
      </div>
    );
  }

  const primaryAssignment =
    employee.assignments.find((a) => a.is_primary === 1) ||
    employee.assignments[0];
  const isBirthday = checkBirthday(employee.birthdate);

  const copyToClipboard = async (text: string, field: string) => {
    await navigator.clipboard.writeText(text);
    setCopiedField(field);
    setTimeout(() => setCopiedField(null), 1500);
  };

  const getInitials = (name: string) => {
    const parts = name.split(" ");
    return parts.length > 1
      ? (parts[0][0] + (parts[1][0] || "")).toUpperCase()
      : (parts[0][0] || "").toUpperCase();
  };

  const calculateServiceYears = (startDate?: string) => {
    if (!startDate) return null;
    const start = new Date(startDate);
    const now = new Date();
    const years = Math.floor(
      (now.getTime() - start.getTime()) / (1000 * 60 * 60 * 24 * 365)
    );
    const months = Math.floor(
      ((now.getTime() - start.getTime()) % (1000 * 60 * 60 * 24 * 365)) /
        (1000 * 60 * 60 * 24 * 30)
    );
    if (years > 0) return `${years} ปี ${months} เดือน`;
    return `${months} เดือน`;
  };

  const formatDate = (dateString?: string) => {
    if (!dateString) return "-";
    return new Date(dateString).toLocaleDateString("th-TH", {
      year: "numeric",
      month: "long",
      day: "numeric",
    });
  };

  return (
    <div className="h-full flex flex-col bg-card animate-fade-in">
      {/* Header */}
      <div className="p-6 border-b border-border">
        <div className="flex items-start justify-between mb-6">
          <button
            onClick={onClose}
            className="lg:hidden w-8 h-8 rounded-lg hover:bg-accent flex items-center justify-center transition-colors"
          >
            <X className="w-4 h-4" />
          </button>
          <div className="flex gap-2">
            <button
              onClick={onToggleFavorite}
              className={cn(
                "w-10 h-10 rounded-xl flex items-center justify-center transition-all",
                isFavorite
                  ? "bg-amber-100 dark:bg-amber-900/30 text-amber-500"
                  : "bg-accent text-muted-foreground hover:text-amber-500"
              )}
            >
              <Star className={cn("w-5 h-5", isFavorite && "fill-amber-500")} />
            </button>
          </div>
        </div>

        {/* Profile */}
        <div className="flex flex-col items-center text-center">
          <div className="relative mb-4">
            <div className="w-24 h-24 rounded-full bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-700 dark:to-slate-800 flex items-center justify-center text-2xl font-semibold text-muted-foreground overflow-hidden border-4 border-background shadow-lg">
              {employee.image ? (
                <img
                  src={`/uploads/employees/${employee.image}`}
                  alt={employee.name}
                  className="w-full h-full object-cover"
                />
              ) : (
                <span>{getInitials(employee.name)}</span>
              )}
            </div>
            {isBirthday && (
              <div className="absolute -top-2 -right-2 w-8 h-8 bg-amber-100 dark:bg-amber-900/30 rounded-full flex items-center justify-center animate-bounce">
                <Crown className="w-5 h-5 text-amber-500 fill-amber-500" />
              </div>
            )}
            {employee.employment_status === "inactive" && (
              <div className="absolute -bottom-1 -right-1 px-2 py-0.5 bg-slate-200 dark:bg-slate-700 rounded-full text-[10px] font-medium text-slate-600 dark:text-slate-400">
                ลาออก
              </div>
            )}
          </div>

          <h2 className="text-xl font-bold text-foreground mb-1">
            {employee.name}
          </h2>
          {employee.name_th && (
            <p className="text-sm text-muted-foreground mb-2">
              {employee.name_th}
            </p>
          )}
          <div className="flex items-center gap-2 text-sm">
            <span className="font-mono text-muted-foreground bg-muted px-2 py-0.5 rounded">
              {employee.id}
            </span>
            {employee.position && (
              <span className="text-emerald-600 dark:text-emerald-400 font-medium">
                {POSITION_ABBREVIATIONS[employee.position] || employee.position}
              </span>
            )}
          </div>
        </div>
      </div>

      {/* Content */}
      <div className="flex-1 overflow-y-auto p-6 space-y-6">
        {/* Contact Section */}
        <section>
          <h3 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-3">
            ข้อมูลติดต่อ
          </h3>
          <div className="space-y-2">
            {employee.phone && (
              <ContactRow
                icon={<Phone className="w-4 h-4" />}
                label="โทรศัพท์"
                value={employee.phone}
                onCopy={() => copyToClipboard(employee.phone!, "phone")}
                copied={copiedField === "phone"}
              />
            )}
            {primaryAssignment?.email && (
              <ContactRow
                icon={<Mail className="w-4 h-4" />}
                label="อีเมล"
                value={primaryAssignment.email}
                onCopy={() => copyToClipboard(primaryAssignment.email, "email")}
                copied={copiedField === "email"}
              />
            )}
          </div>
        </section>

        {/* Assignments Section */}
        <section>
          <h3 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-3">
            สังกัด
          </h3>
          <div className="space-y-2">
            {employee.assignments.map((assignment, index) => (
              <div
                key={`${assignment.company}-${assignment.department}-${index}`}
                className={cn(
                  "p-3 rounded-xl border transition-all",
                  assignment.is_primary
                    ? "bg-primary/5 border-primary/20"
                    : "bg-muted/50 border-border"
                )}
              >
                <div className="flex items-center gap-2 mb-1">
                  <Building2 className="w-4 h-4 text-muted-foreground" />
                  <span
                    className={cn(
                      "font-semibold text-sm",
                      assignment.company === "PTA" &&
                        "text-emerald-600 dark:text-emerald-400",
                      assignment.company === "PT4" &&
                        "text-red-600 dark:text-red-400",
                      assignment.company === "PTE" &&
                        "text-blue-600 dark:text-blue-400"
                    )}
                  >
                    {assignment.company}
                  </span>
                  {assignment.is_primary === 1 && (
                    <span className="ml-auto px-1.5 py-0.5 text-[10px] font-medium bg-primary/10 text-primary rounded">
                      หลัก
                    </span>
                  )}
                </div>
                <p className="text-sm text-muted-foreground ml-6">
                  {assignment.department}
                </p>
                {assignment.email && (
                  <div className="flex items-center gap-2 mt-2 ml-6">
                    <Mail className="w-3 h-3 text-muted-foreground" />
                    <span className="text-xs text-muted-foreground">
                      {assignment.email}
                    </span>
                    <button
                      onClick={() =>
                        copyToClipboard(
                          assignment.email,
                          `email-${assignment.company}`
                        )
                      }
                      className={cn(
                        "p-1 rounded hover:bg-accent transition-colors",
                        copiedField === `email-${assignment.company}` &&
                          "text-emerald-500"
                      )}
                    >
                      {copiedField === `email-${assignment.company}` ? (
                        <Check className="w-3 h-3" />
                      ) : (
                        <Copy className="w-3 h-3" />
                      )}
                    </button>
                  </div>
                )}
              </div>
            ))}
          </div>
        </section>

        {/* Work Info Section */}
        <section>
          <h3 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-3">
            ข้อมูลการทำงาน
          </h3>
          <div className="space-y-3">
            {employee.start_date && (
              <InfoRow
                icon={<Calendar className="w-4 h-4" />}
                label="วันเริ่มงาน"
                value={formatDate(employee.start_date)}
              />
            )}
            {employee.start_date && (
              <InfoRow
                icon={<Briefcase className="w-4 h-4" />}
                label="อายุงาน"
                value={calculateServiceYears(employee.start_date) || "-"}
              />
            )}
            {employee.birthdate && (
              <InfoRow
                icon={
                  <Calendar
                    className={cn(
                      "w-4 h-4",
                      isBirthday && "text-amber-500"
                    )}
                  />
                }
                label="วันเกิด"
                value={
                  formatDate(employee.birthdate) +
                  (isBirthday ? " 🎂" : "")
                }
              />
            )}
          </div>
        </section>
      </div>
    </div>
  );
}

function ContactRow({
  icon,
  label,
  value,
  onCopy,
  copied,
}: {
  icon: React.ReactNode;
  label: string;
  value: string;
  onCopy: () => void;
  copied: boolean;
}) {
  return (
    <div className="flex items-center gap-3 p-3 rounded-xl bg-muted/50 hover:bg-muted transition-colors group">
      <div className="text-muted-foreground">{icon}</div>
      <div className="flex-1 min-w-0">
        <p className="text-xs text-muted-foreground">{label}</p>
        <p className="text-sm font-medium truncate">{value}</p>
      </div>
      <button
        onClick={onCopy}
        className={cn(
          "p-2 rounded-lg transition-all",
          copied
            ? "bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600"
            : "opacity-0 group-hover:opacity-100 hover:bg-accent text-muted-foreground"
        )}
      >
        {copied ? <Check className="w-4 h-4" /> : <Copy className="w-4 h-4" />}
      </button>
    </div>
  );
}

function InfoRow({
  icon,
  label,
  value,
}: {
  icon: React.ReactNode;
  label: string;
  value: string;
}) {
  return (
    <div className="flex items-center gap-3">
      <div className="text-muted-foreground">{icon}</div>
      <div className="flex-1">
        <p className="text-xs text-muted-foreground">{label}</p>
        <p className="text-sm font-medium">{value}</p>
      </div>
    </div>
  );
}

function checkBirthday(dateString?: string) {
  if (!dateString) return false;
  const today = new Date();
  const birthDate = new Date(dateString);
  return (
    today.getDate() === birthDate.getDate() &&
    today.getMonth() === birthDate.getMonth()
  );
}
