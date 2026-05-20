"use client";

import { cn } from "@/lib/utils";
import type { Company } from "@/types/employee";
import { DEPARTMENTS } from "@/types/employee";
import {
  Building2,
  ChevronDown,
  Folder,
  Heart,
  Moon,
  Search,
  Sun,
  Users,
} from "lucide-react";
import { useState } from "react";

interface SidebarProps {
  activeCompany: Company;
  onCompanyChange: (company: Company) => void;
  activeDepartment: string | null;
  onDepartmentChange: (department: string | null) => void;
  showFavorites: boolean;
  onToggleFavorites: () => void;
  searchQuery: string;
  onSearchChange: (query: string) => void;
  employeeCounts: {
    total: number;
    byCompany: Record<Company, number>;
    byDepartment: Record<string, number>;
    favorites: number;
  };
}

export function Sidebar({
  activeCompany,
  onCompanyChange,
  activeDepartment,
  onDepartmentChange,
  showFavorites,
  onToggleFavorites,
  searchQuery,
  onSearchChange,
  employeeCounts,
}: SidebarProps) {
  const [isDark, setIsDark] = useState(
    () => typeof document !== "undefined" && document.documentElement.classList.contains("dark")
  );
  const [expandedSections, setExpandedSections] = useState({
    companies: true,
    departments: true,
  });

  const toggleDarkMode = () => {
    document.documentElement.classList.toggle("dark");
    setIsDark(!isDark);
  };

  const toggleSection = (section: "companies" | "departments") => {
    setExpandedSections((prev) => ({
      ...prev,
      [section]: !prev[section],
    }));
  };

  const companies: { id: Company; name: string; color: string }[] = [
    { id: "All", name: "ทั้งหมด", color: "text-muted-foreground" },
    { id: "PTA", name: "PTA", color: "text-emerald-600 dark:text-emerald-400" },
    { id: "PT4", name: "PT4", color: "text-red-600 dark:text-red-400" },
    { id: "PTE", name: "PTE", color: "text-blue-600 dark:text-blue-400" },
  ];

  return (
    <aside className="w-64 h-screen bg-sidebar border-r border-sidebar-border flex flex-col shrink-0 sticky top-0">
      {/* Header */}
      <div className="p-4 border-b border-sidebar-border">
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center gap-2">
            <div className="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center">
              <Users className="w-4 h-4 text-primary" />
            </div>
            <span className="font-semibold text-sm">Directory</span>
          </div>
          <button
            onClick={toggleDarkMode}
            className="w-8 h-8 rounded-lg hover:bg-sidebar-accent flex items-center justify-center transition-colors"
            aria-label="Toggle dark mode"
          >
            {isDark ? (
              <Sun className="w-4 h-4 text-amber-500" />
            ) : (
              <Moon className="w-4 h-4 text-slate-500" />
            )}
          </button>
        </div>

        {/* Search */}
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
          <input
            type="text"
            placeholder="ค้นหาพนักงาน..."
            value={searchQuery}
            onChange={(e) => onSearchChange(e.target.value)}
            className="w-full pl-9 pr-3 py-2 text-sm bg-sidebar-accent/50 border border-sidebar-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all placeholder:text-muted-foreground"
          />
          <kbd className="absolute right-2 top-1/2 -translate-y-1/2 px-1.5 py-0.5 text-[10px] bg-sidebar-accent text-muted-foreground rounded hidden sm:inline-block">
            ⌘K
          </kbd>
        </div>
      </div>

      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto py-2 px-2">
        {/* Favorites */}
        <button
          onClick={onToggleFavorites}
          className={cn(
            "w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all mb-1",
            showFavorites
              ? "bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300"
              : "hover:bg-sidebar-accent text-sidebar-foreground"
          )}
        >
          <Heart
            className={cn(
              "w-4 h-4",
              showFavorites ? "fill-amber-500 text-amber-500" : ""
            )}
          />
          <span className="flex-1 text-left">รายการโปรด</span>
          <span className="text-xs px-1.5 py-0.5 rounded bg-sidebar-accent text-muted-foreground">
            {employeeCounts.favorites}
          </span>
        </button>

        {/* Companies Section */}
        <div className="mt-4">
          <button
            onClick={() => toggleSection("companies")}
            className="w-full flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-muted-foreground uppercase tracking-wider hover:text-foreground transition-colors"
          >
            <ChevronDown
              className={cn(
                "w-3 h-3 transition-transform",
                !expandedSections.companies && "-rotate-90"
              )}
            />
            <Building2 className="w-3 h-3" />
            บริษัท
          </button>

          {expandedSections.companies && (
            <div className="mt-1 space-y-0.5">
              {companies.map((company) => (
                <button
                  key={company.id}
                  onClick={() => {
                    onCompanyChange(company.id);
                    onDepartmentChange(null);
                  }}
                  className={cn(
                    "w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all",
                    activeCompany === company.id && !showFavorites
                      ? "bg-primary/10 text-primary font-medium"
                      : "hover:bg-sidebar-accent text-sidebar-foreground"
                  )}
                >
                  <span
                    className={cn(
                      "w-2 h-2 rounded-full",
                      company.id === "All"
                        ? "bg-slate-400"
                        : company.id === "PTA"
                          ? "bg-emerald-500"
                          : company.id === "PT4"
                            ? "bg-red-500"
                            : "bg-blue-500"
                    )}
                  />
                  <span className={cn("flex-1 text-left", company.color)}>
                    {company.name}
                  </span>
                  <span className="text-xs text-muted-foreground">
                    {company.id === "All"
                      ? employeeCounts.total
                      : employeeCounts.byCompany[company.id]}
                  </span>
                </button>
              ))}
            </div>
          )}
        </div>

        {/* Departments Section */}
        <div className="mt-4">
          <button
            onClick={() => toggleSection("departments")}
            className="w-full flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-muted-foreground uppercase tracking-wider hover:text-foreground transition-colors"
          >
            <ChevronDown
              className={cn(
                "w-3 h-3 transition-transform",
                !expandedSections.departments && "-rotate-90"
              )}
            />
            <Folder className="w-3 h-3" />
            แผนก
          </button>

          {expandedSections.departments && (
            <div className="mt-1 space-y-0.5 max-h-[300px] overflow-y-auto">
              <button
                onClick={() => onDepartmentChange(null)}
                className={cn(
                  "w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all",
                  activeDepartment === null && !showFavorites
                    ? "bg-primary/10 text-primary font-medium"
                    : "hover:bg-sidebar-accent text-sidebar-foreground"
                )}
              >
                <span className="flex-1 text-left">ทุกแผนก</span>
              </button>
              {DEPARTMENTS.map((dept) => (
                <button
                  key={dept}
                  onClick={() => onDepartmentChange(dept)}
                  className={cn(
                    "w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all",
                    activeDepartment === dept
                      ? "bg-primary/10 text-primary font-medium"
                      : "hover:bg-sidebar-accent text-sidebar-foreground"
                  )}
                >
                  <span className="flex-1 text-left truncate">{dept}</span>
                  <span className="text-xs text-muted-foreground">
                    {employeeCounts.byDepartment[dept] || 0}
                  </span>
                </button>
              ))}
            </div>
          )}
        </div>
      </nav>

      {/* Footer */}
      <div className="p-4 border-t border-sidebar-border">
        <div className="text-xs text-muted-foreground text-center">
          <p>
            {employeeCounts.total} พนักงาน • อัปเดตล่าสุด{" "}
            {new Date().toLocaleDateString("th-TH")}
          </p>
        </div>
      </div>
    </aside>
  );
}
