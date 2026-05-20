"use client";

import { cn } from "@/lib/utils";
import type { Company } from "@/types/employee";
import { Menu, Moon, Search, Sun, Users, X } from "lucide-react";
import { useState } from "react";

interface MobileHeaderProps {
  searchQuery: string;
  onSearchChange: (query: string) => void;
  activeCompany: Company;
  onCompanyChange: (company: Company) => void;
  onOpenSidebar: () => void;
  employeeCount: number;
}

export function MobileHeader({
  searchQuery,
  onSearchChange,
  activeCompany,
  onCompanyChange,
  onOpenSidebar,
  employeeCount,
}: MobileHeaderProps) {
  const [isDark, setIsDark] = useState(
    () => typeof document !== "undefined" && document.documentElement.classList.contains("dark")
  );
  const [showSearch, setShowSearch] = useState(false);

  const toggleDarkMode = () => {
    document.documentElement.classList.toggle("dark");
    setIsDark(!isDark);
  };

  const companies: { id: Company; name: string }[] = [
    { id: "All", name: "ทั้งหมด" },
    { id: "PTA", name: "PTA" },
    { id: "PT4", name: "PT4" },
    { id: "PTE", name: "PTE" },
  ];

  return (
    <header className="lg:hidden sticky top-0 z-20 bg-background/80 backdrop-blur-xl border-b border-border">
      <div className="flex items-center justify-between p-4">
        <button
          onClick={onOpenSidebar}
          className="w-10 h-10 rounded-xl bg-accent flex items-center justify-center"
        >
          <Menu className="w-5 h-5" />
        </button>

        <div className="flex items-center gap-2">
          <Users className="w-5 h-5 text-primary" />
          <span className="font-semibold">Directory</span>
          <span className="text-xs text-muted-foreground bg-muted px-2 py-0.5 rounded-full">
            {employeeCount}
          </span>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={() => setShowSearch(!showSearch)}
            className="w-10 h-10 rounded-xl hover:bg-accent flex items-center justify-center transition-colors"
          >
            {showSearch ? (
              <X className="w-5 h-5" />
            ) : (
              <Search className="w-5 h-5" />
            )}
          </button>
          <button
            onClick={toggleDarkMode}
            className="w-10 h-10 rounded-xl hover:bg-accent flex items-center justify-center transition-colors"
          >
            {isDark ? (
              <Sun className="w-5 h-5 text-amber-500" />
            ) : (
              <Moon className="w-5 h-5" />
            )}
          </button>
        </div>
      </div>

      {/* Search Bar */}
      {showSearch && (
        <div className="px-4 pb-4 animate-fade-in">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            <input
              type="text"
              placeholder="ค้นหาพนักงาน..."
              value={searchQuery}
              onChange={(e) => onSearchChange(e.target.value)}
              autoFocus
              className="w-full pl-10 pr-4 py-3 text-sm bg-muted border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
            />
          </div>
        </div>
      )}

      {/* Company Filter Pills */}
      <div className="flex gap-2 px-4 pb-4 overflow-x-auto scrollbar-hide">
        {companies.map((company) => (
          <button
            key={company.id}
            onClick={() => onCompanyChange(company.id)}
            className={cn(
              "px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap transition-all",
              activeCompany === company.id
                ? company.id === "PTA"
                  ? "bg-emerald-500 text-white"
                  : company.id === "PT4"
                    ? "bg-red-500 text-white"
                    : company.id === "PTE"
                      ? "bg-blue-500 text-white"
                      : "bg-primary text-primary-foreground"
                : "bg-muted text-muted-foreground hover:bg-accent"
            )}
          >
            {company.name}
          </button>
        ))}
      </div>
    </header>
  );
}
