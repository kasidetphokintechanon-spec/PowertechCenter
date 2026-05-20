"use client";

import { cn } from "@/lib/utils";
import type { Employee } from "@/types/employee";
import { POSITION_ABBREVIATIONS } from "@/types/employee";
import {
  Briefcase,
  Copy,
  Crown,
  Mail,
  Phone,
  Star,
} from "lucide-react";
import { useState } from "react";

const CURRENT_TIME_MS = Date.now();

interface EmployeeCardProps {
  employee: Employee;
  isSelected?: boolean;
  isFavorite?: boolean;
  onSelect?: () => void;
  onToggleFavorite?: () => void;
  activeCompany?: string;
  animationDelay?: number;
}

export function EmployeeCard({
  employee,
  isSelected,
  isFavorite,
  onSelect,
  onToggleFavorite,
  activeCompany = "All",
  animationDelay = 0,
}: EmployeeCardProps) {
  const [copiedField, setCopiedField] = useState<string | null>(null);

  // Get relevant assignment based on active company
  const relevantAssignment =
    activeCompany !== "All"
      ? employee.assignments.find((a) => a.company === activeCompany) ||
        employee.assignments.find((a) => a.is_primary === 1) ||
        employee.assignments[0]
      : employee.assignments.find((a) => a.is_primary === 1) ||
        employee.assignments[0];

  const isNew =
    employee.created_at &&
    (CURRENT_TIME_MS - new Date(employee.created_at).getTime()) /
      (1000 * 60 * 60 * 24) <
      7;
  const isInactive = employee.employment_status === "inactive";
  const isBirthday = checkBirthday(employee.birthdate);

  const getInitials = (name: string) => {
    const parts = name.split(" ");
    return parts.length > 1
      ? (parts[0][0] + (parts[1][0] || "")).toUpperCase()
      : (parts[0][0] || "").toUpperCase();
  };

  const getPositionAbbr = (position: string) => {
    return POSITION_ABBREVIATIONS[position] || position?.slice(0, 6) || "";
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

  const copyToClipboard = async (text: string, field: string) => {
    await navigator.clipboard.writeText(text);
    setCopiedField(field);
    setTimeout(() => setCopiedField(null), 1500);
  };

  const companyColor =
    relevantAssignment?.company === "PTA"
      ? "border-l-emerald-500"
      : relevantAssignment?.company === "PT4"
        ? "border-l-red-500"
        : relevantAssignment?.company === "PTE"
          ? "border-l-blue-500"
          : "border-l-slate-300";

  return (
    <div
      onClick={onSelect}
      style={{ animationDelay: `${animationDelay}ms` }}
      className={cn(
        "group relative bg-card rounded-xl border border-border shadow-sm hover:shadow-md transition-all duration-200 cursor-pointer animate-fade-in",
        "border-l-4",
        companyColor,
        isSelected && "ring-2 ring-primary shadow-lg bg-primary/5",
        isInactive && "opacity-60"
      )}
    >
      {/* Selection Indicator */}
      {isSelected && (
        <div className="absolute -left-3 top-1/2 -translate-y-1/2 w-6 h-6 bg-primary rounded-full flex items-center justify-center text-primary-foreground animate-scale-in">
          <svg
            className="w-3 h-3"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={3}
              d="M5 13l4 4L19 7"
            />
          </svg>
        </div>
      )}

      {/* Birthday Crown */}
      {isBirthday && (
        <div className="absolute -top-3 -right-1 text-amber-400 animate-bounce z-10">
          <Crown className="w-6 h-6 fill-amber-400 drop-shadow-lg" />
        </div>
      )}

      <div className="p-4 flex items-center gap-4">
        {/* Avatar */}
        <div className="relative shrink-0">
          <div
            className={cn(
              "w-12 h-12 rounded-full bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-700 dark:to-slate-800 flex items-center justify-center text-sm font-semibold text-muted-foreground overflow-hidden border-2 border-background shadow-sm",
              isSelected && "border-primary"
            )}
          >
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
        </div>

        {/* Info */}
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 mb-0.5">
            <h3 className="font-semibold text-sm text-foreground truncate">
              {employee.name}
            </h3>
            {isNew && (
              <span className="px-1.5 py-0.5 text-[10px] font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 rounded-full">
                New
              </span>
            )}
            {isInactive && (
              <span className="px-1.5 py-0.5 text-[10px] font-medium bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 rounded-full">
                ลาออก
              </span>
            )}
          </div>
          {employee.name_th && (
            <p className="text-xs text-muted-foreground truncate mb-0.5">
              {employee.name_th}
            </p>
          )}
          <div className="flex items-center gap-2 text-xs">
            <span className="font-mono text-muted-foreground">
              {employee.id}
            </span>
            {employee.position && (
              <>
                <span className="text-muted-foreground">•</span>
                <span
                  className="text-emerald-600 dark:text-emerald-400 font-medium"
                  title={employee.position}
                >
                  {getPositionAbbr(employee.position)}
                </span>
              </>
            )}
          </div>
        </div>

        {/* Company & Department */}
        <div className="hidden sm:block text-right shrink-0">
          <p
            className={cn(
              "text-sm font-medium",
              relevantAssignment?.company === "PTA" &&
                "text-emerald-600 dark:text-emerald-400",
              relevantAssignment?.company === "PT4" &&
                "text-red-600 dark:text-red-400",
              relevantAssignment?.company === "PTE" &&
                "text-blue-600 dark:text-blue-400"
            )}
          >
            {relevantAssignment?.company}
          </p>
          <p className="text-xs text-muted-foreground truncate max-w-[120px]">
            {relevantAssignment?.department}
          </p>
        </div>

        {/* Contact Info */}
        <div className="hidden md:flex flex-col gap-1 shrink-0 min-w-[180px]">
          {relevantAssignment?.email && (
            <div className="flex items-center gap-2 text-xs text-muted-foreground group/email">
              <Mail className="w-3 h-3" />
              <span className="truncate">{relevantAssignment.email}</span>
              <button
                onClick={(e) => {
                  e.stopPropagation();
                  copyToClipboard(relevantAssignment.email, "email");
                }}
                className={cn(
                  "opacity-0 group-hover/email:opacity-100 transition-opacity p-1 rounded hover:bg-accent",
                  copiedField === "email" && "opacity-100 text-emerald-500"
                )}
              >
                <Copy className="w-3 h-3" />
              </button>
            </div>
          )}
          {employee.phone && (
            <div className="flex items-center gap-2 text-xs text-muted-foreground group/phone">
              <Phone className="w-3 h-3" />
              <span>{employee.phone}</span>
              <button
                onClick={(e) => {
                  e.stopPropagation();
                  copyToClipboard(employee.phone!, "phone");
                }}
                className={cn(
                  "opacity-0 group-hover/phone:opacity-100 transition-opacity p-1 rounded hover:bg-accent",
                  copiedField === "phone" && "opacity-100 text-emerald-500"
                )}
              >
                <Copy className="w-3 h-3" />
              </button>
            </div>
          )}
        </div>

        {/* Service Years */}
        <div className="hidden lg:flex items-center gap-1 text-xs text-primary/70 shrink-0">
          <Briefcase className="w-3 h-3" />
          <span>{calculateServiceYears(employee.start_date) || "-"}</span>
        </div>

        {/* Actions */}
        <div className="flex items-center gap-1 shrink-0">
          <button
            onClick={(e) => {
              e.stopPropagation();
              onToggleFavorite?.();
            }}
            className={cn(
              "w-8 h-8 rounded-lg flex items-center justify-center transition-all",
              isFavorite
                ? "text-amber-500"
                : "text-muted-foreground opacity-0 group-hover:opacity-100 hover:bg-accent"
            )}
          >
            <Star className={cn("w-4 h-4", isFavorite && "fill-amber-500")} />
          </button>
        </div>
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
