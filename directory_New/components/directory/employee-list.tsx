"use client";

import React from "react"

import { cn } from "@/lib/utils";
import type { Company, Employee } from "@/types/employee";
import { POSITION_RANKS } from "@/types/employee";
import { ChevronDown, Crown, Heart, Users } from "lucide-react";
import { useMemo, useState } from "react";
import { EmployeeCard } from "./employee-card";

const isCancelledManagementDepartmentName = (value: unknown) => {
  const name = String(value ?? "").trim().toLowerCase();
  return name === "management" || name === "mangement" || name === "managment";
};

interface EmployeeListProps {
  employees: Employee[];
  favorites: Set<string>;
  onToggleFavorite: (id: string) => void;
  selectedEmployee: string | null;
  onSelectEmployee: (id: string) => void;
  activeCompany: Company;
  activeDepartment: string | null;
  showFavorites: boolean;
}

export function EmployeeList({
  employees,
  favorites,
  onToggleFavorite,
  selectedEmployee,
  onSelectEmployee,
  activeCompany,
  activeDepartment,
  showFavorites,
}: EmployeeListProps) {
  const [collapsedSections, setCollapsedSections] = useState<Set<string>>(
    new Set()
  );

  const toggleSection = (section: string) => {
    setCollapsedSections((prev) => {
      const next = new Set(prev);
      if (next.has(section)) {
        next.delete(section);
      } else {
        next.add(section);
      }
      return next;
    });
  };

  // Filter and group employees
  const { favoritesList, managementList, departmentGroups, inactiveList } =
    useMemo(() => {
      // Filter by company
      let filtered = employees;
      if (activeCompany !== "All") {
        filtered = employees.filter((emp) =>
          emp.assignments.some((a) => a.company === activeCompany)
        );
      }

      // Filter by department
      if (activeDepartment) {
        filtered = filtered.filter((emp) =>
          emp.assignments.some((a) => a.department === activeDepartment)
        );
      }

      // Favorites
      const favoritesList = filtered.filter(
        (emp) =>
          favorites.has(emp.id) && emp.employment_status === "active"
      );

      // Active employees
      const active = filtered.filter(
        (emp) => emp.employment_status === "active"
      );
      const inactive = filtered.filter(
        (emp) => emp.employment_status === "inactive"
      );

      // Management level (rank < 20)
      const managementPositions = Object.keys(POSITION_RANKS).filter(
        (p) => POSITION_RANKS[p] < 20
      );
      const management = active
        .filter(
          (emp) => emp.position && managementPositions.includes(emp.position)
        )
        .sort((a, b) => {
          const rankA = POSITION_RANKS[a.position || ""] || 999;
          const rankB = POSITION_RANKS[b.position || ""] || 999;
          return rankA - rankB || a.name.localeCompare(b.name, "th");
        });

      // Group by department (non-management)
      const nonManagement = active.filter(
        (emp) => !emp.position || !managementPositions.includes(emp.position)
      );
      const grouped: Record<string, Employee[]> = {};
      for (const emp of nonManagement) {
        const dept =
          activeCompany !== "All"
            ? emp.assignments.find((a) => a.company === activeCompany)
                ?.department
            : (
                emp.assignments.find((a) => a.is_primary === 1) ||
                emp.assignments[0]
              )?.department;
        const deptName = String(dept ?? "").trim();
        if (!deptName) continue;
        if (isCancelledManagementDepartmentName(deptName)) continue;
        if (!grouped[deptName]) grouped[deptName] = [];
        grouped[deptName].push(emp);
      }

      // Sort each department by position rank then name
      for (const dept of Object.keys(grouped)) {
        grouped[dept].sort((a, b) => {
          const rankA = POSITION_RANKS[a.position || ""] || 999;
          const rankB = POSITION_RANKS[b.position || ""] || 999;
          return rankA - rankB || a.name.localeCompare(b.name, "th");
        });
      }

      return {
        favoritesList,
        managementList: management,
        departmentGroups: grouped,
        inactiveList: inactive.sort((a, b) => a.name.localeCompare(b.name, "th")),
      };
    }, [employees, favorites, activeCompany, activeDepartment]);

  if (showFavorites) {
    return (
      <div className="space-y-3">
        <SectionHeader
          icon={<Heart className="w-4 h-4 fill-amber-500 text-amber-500" />}
          title="รายการโปรด"
          count={favoritesList.length}
          variant="favorites"
        />
        {favoritesList.length === 0 ? (
          <EmptyState message="ยังไม่มีรายการโปรด กดดาวที่การ์ดพนักงานเพื่อเพิ่มรายการโปรด" />
        ) : (
          <div className="space-y-2">
            {favoritesList.map((emp, index) => (
              <EmployeeCard
                key={emp.id}
                employee={emp}
                isSelected={selectedEmployee === emp.id}
                isFavorite={favorites.has(emp.id)}
                onSelect={() => onSelectEmployee(emp.id)}
                onToggleFavorite={() => onToggleFavorite(emp.id)}
                activeCompany={activeCompany}
                animationDelay={index * 30}
              />
            ))}
          </div>
        )}
      </div>
    );
  }

  const totalActive =
    managementList.length +
    Object.values(departmentGroups).reduce((sum, arr) => sum + arr.length, 0);

  if (totalActive === 0 && inactiveList.length === 0) {
    return <EmptyState message="ไม่พบข้อมูลพนักงานตามเงื่อนไขที่เลือก" />;
  }

  return (
    <div className="space-y-6">
      {/* Favorites Section */}
      {favoritesList.length > 0 && (
        <div>
          <CollapsibleSection
            id="favorites"
            icon={<Heart className="w-4 h-4 fill-amber-500 text-amber-500" />}
            title="รายการโปรด"
            count={favoritesList.length}
            variant="favorites"
            collapsed={collapsedSections.has("favorites")}
            onToggle={() => toggleSection("favorites")}
          >
            <div className="space-y-2">
              {favoritesList.map((emp, index) => (
                <EmployeeCard
                  key={emp.id}
                  employee={emp}
                  isSelected={selectedEmployee === emp.id}
                  isFavorite={true}
                  onSelect={() => onSelectEmployee(emp.id)}
                  onToggleFavorite={() => onToggleFavorite(emp.id)}
                  activeCompany={activeCompany}
                  animationDelay={index * 30}
                />
              ))}
            </div>
          </CollapsibleSection>
        </div>
      )}

      {/* Management Section */}
      {managementList.length > 0 && (
        <CollapsibleSection
          id="management"
          icon={<Crown className="w-4 h-4 text-indigo-500" />}
          title="ระดับ Management"
          count={managementList.length}
          variant="management"
          collapsed={collapsedSections.has("management")}
          onToggle={() => toggleSection("management")}
        >
          <div className="space-y-2">
            {managementList.map((emp, index) => (
              <EmployeeCard
                key={emp.id}
                employee={emp}
                isSelected={selectedEmployee === emp.id}
                isFavorite={favorites.has(emp.id)}
                onSelect={() => onSelectEmployee(emp.id)}
                onToggleFavorite={() => onToggleFavorite(emp.id)}
                activeCompany={activeCompany}
                animationDelay={index * 30}
              />
            ))}
          </div>
        </CollapsibleSection>
      )}

      {/* Department Sections */}
      {Object.entries(departmentGroups).map(([dept, empList]) => (
        <CollapsibleSection
          key={dept}
          id={dept}
          icon={<Users className="w-4 h-4 text-slate-500" />}
          title={dept}
          count={empList.length}
          variant="department"
          collapsed={collapsedSections.has(dept)}
          onToggle={() => toggleSection(dept)}
        >
          <div className="space-y-2">
            {empList.map((emp, index) => (
              <EmployeeCard
                key={emp.id}
                employee={emp}
                isSelected={selectedEmployee === emp.id}
                isFavorite={favorites.has(emp.id)}
                onSelect={() => onSelectEmployee(emp.id)}
                onToggleFavorite={() => onToggleFavorite(emp.id)}
                activeCompany={activeCompany}
                animationDelay={index * 30}
              />
            ))}
          </div>
        </CollapsibleSection>
      ))}

      {/* Inactive Section */}
      {inactiveList.length > 0 && (
        <CollapsibleSection
          id="inactive"
          icon={<Users className="w-4 h-4 text-slate-400" />}
          title="พนักงานที่ลาออกแล้ว"
          count={inactiveList.length}
          variant="inactive"
          collapsed={collapsedSections.has("inactive")}
          onToggle={() => toggleSection("inactive")}
        >
          <div className="space-y-2">
            {inactiveList.map((emp, index) => (
              <EmployeeCard
                key={emp.id}
                employee={emp}
                isSelected={selectedEmployee === emp.id}
                isFavorite={favorites.has(emp.id)}
                onSelect={() => onSelectEmployee(emp.id)}
                onToggleFavorite={() => onToggleFavorite(emp.id)}
                activeCompany={activeCompany}
                animationDelay={index * 30}
              />
            ))}
          </div>
        </CollapsibleSection>
      )}
    </div>
  );
}

// Section Header Component
function SectionHeader({
  icon,
  title,
  count,
  variant = "default",
}: {
  icon: React.ReactNode;
  title: string;
  count: number;
  variant?: "favorites" | "management" | "department" | "inactive" | "default";
}) {
  const variantStyles = {
    favorites: "bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300",
    management: "bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300",
    department: "bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-300",
    inactive: "bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400",
    default: "bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-300",
  };

  return (
    <div
      className={cn(
        "flex items-center gap-3 px-4 py-3 rounded-xl",
        variantStyles[variant]
      )}
    >
      {icon}
      <span className="font-semibold">{title}</span>
      <span className="ml-auto text-sm opacity-70">{count} คน</span>
    </div>
  );
}

// Collapsible Section Component
function CollapsibleSection({
  id,
  icon,
  title,
  count,
  variant = "default",
  collapsed,
  onToggle,
  children,
}: {
  id: string;
  icon: React.ReactNode;
  title: string;
  count: number;
  variant?: "favorites" | "management" | "department" | "inactive" | "default";
  collapsed: boolean;
  onToggle: () => void;
  children: React.ReactNode;
}) {
  const variantStyles = {
    favorites: "bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800/50",
    management: "bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800/50",
    department: "bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700",
    inactive: "bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-700",
    default: "bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700",
  };

  return (
    <div>
      <button
        onClick={onToggle}
        className={cn(
          "w-full flex items-center gap-3 px-4 py-3 rounded-xl border transition-all",
          variantStyles[variant],
          "hover:shadow-sm"
        )}
      >
        <ChevronDown
          className={cn(
            "w-4 h-4 transition-transform",
            collapsed && "-rotate-90"
          )}
        />
        {icon}
        <span className="font-semibold">{title}</span>
        <span className="ml-auto text-sm opacity-70">{count} คน</span>
      </button>
      <div
        className={cn(
          "overflow-hidden transition-all duration-300",
          collapsed ? "max-h-0 opacity-0" : "max-h-[5000px] opacity-100 mt-3"
        )}
      >
        {children}
      </div>
    </div>
  );
}

// Empty State Component
function EmptyState({ message }: { message: string }) {
  return (
    <div className="flex flex-col items-center justify-center py-20 text-muted-foreground">
      <div className="w-16 h-16 bg-muted rounded-full flex items-center justify-center mb-4">
        <Users className="w-8 h-8 opacity-50" />
      </div>
      <p className="text-sm text-center max-w-xs">{message}</p>
    </div>
  );
}
