"use client";

import { EmployeeDetail } from "@/components/directory/employee-detail";
import { EmployeeList } from "@/components/directory/employee-list";
import { MobileHeader } from "@/components/directory/mobile-header";
import { Sidebar } from "@/components/directory/sidebar";
import { cn } from "@/lib/utils";
import { mockEmployees } from "@/lib/mock-data";
import type { Company, Employee } from "@/types/employee";
import { X } from "lucide-react";
import { useCallback, useEffect, useMemo, useState } from "react";

const getInitialFavorites = () => {
  if (typeof window === "undefined") return new Set<string>();
  const stored = localStorage.getItem("favoriteEmployees");
  if (!stored) return new Set<string>();
  try {
    return new Set<string>(JSON.parse(stored));
  } catch {
    return new Set<string>();
  }
};

export default function DirectoryPage() {
  // State
  const [employees] = useState<Employee[]>(mockEmployees);
  const [searchQuery, setSearchQuery] = useState("");
  const [activeCompany, setActiveCompany] = useState<Company>("All");
  const [activeDepartment, setActiveDepartment] = useState<string | null>(null);
  const [showFavorites, setShowFavorites] = useState(false);
  const [favorites, setFavorites] = useState<Set<string>>(getInitialFavorites);
  const [selectedEmployee, setSelectedEmployee] = useState<string | null>(null);
  const [showMobileSidebar, setShowMobileSidebar] = useState(false);
  const [showMobileDetail, setShowMobileDetail] = useState(false);

  // Save favorites to localStorage
  const toggleFavorite = useCallback((id: string) => {
    setFavorites((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      localStorage.setItem("favoriteEmployees", JSON.stringify([...next]));
      return next;
    });
  }, []);

  // Filter employees based on search query
  const filteredEmployees = useMemo(() => {
    if (!searchQuery.trim()) return employees;
    const query = searchQuery.toLowerCase();
    return employees.filter(
      (emp) =>
        emp.name.toLowerCase().includes(query) ||
        emp.name_th?.toLowerCase().includes(query) ||
        emp.id.toLowerCase().includes(query) ||
        emp.phone?.includes(query) ||
        emp.position?.toLowerCase().includes(query) ||
        emp.assignments.some(
          (a) =>
            a.email.toLowerCase().includes(query) ||
            a.department.toLowerCase().includes(query) ||
            a.company.toLowerCase().includes(query)
        )
    );
  }, [employees, searchQuery]);

  // Calculate counts
  const employeeCounts = useMemo(() => {
    const active = employees.filter((e) => e.employment_status === "active");
    const byCompany: Record<Company, number> = {
      All: active.length,
      PTA: 0,
      PT4: 0,
      PTE: 0,
    };
    const byDepartment: Record<string, number> = {};

    for (const emp of active) {
      for (const assignment of emp.assignments) {
        byCompany[assignment.company as Company]++;
        byDepartment[assignment.department] =
          (byDepartment[assignment.department] || 0) + 1;
      }
    }

    return {
      total: active.length,
      byCompany,
      byDepartment,
      favorites: [...favorites].filter((id) =>
        active.some((e) => e.id === id)
      ).length,
    };
  }, [employees, favorites]);

  // Get selected employee details
  const selectedEmployeeData = useMemo(
    () => employees.find((e) => e.id === selectedEmployee) || null,
    [employees, selectedEmployee]
  );

  // Handle employee selection
  const handleSelectEmployee = (id: string) => {
    setSelectedEmployee(id);
    setShowMobileDetail(true);
  };

  // Handle company change
  const handleCompanyChange = (company: Company) => {
    setActiveCompany(company);
    setShowFavorites(false);
  };

  // Handle department change
  const handleDepartmentChange = (department: string | null) => {
    setActiveDepartment(department);
    setShowFavorites(false);
  };

  // Handle toggle favorites view
  const handleToggleFavorites = () => {
    setShowFavorites(!showFavorites);
  };

  // Keyboard shortcut for search
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key === "k") {
        e.preventDefault();
        const searchInput = document.querySelector(
          'input[type="text"]'
        ) as HTMLInputElement;
        searchInput?.focus();
      }
      if (e.key === "Escape") {
        setSelectedEmployee(null);
        setShowMobileDetail(false);
      }
    };
    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, []);

  return (
    <div className="flex h-screen bg-background overflow-hidden">
      {/* Desktop Sidebar */}
      <div className="hidden lg:block">
        <Sidebar
          activeCompany={activeCompany}
          onCompanyChange={handleCompanyChange}
          activeDepartment={activeDepartment}
          onDepartmentChange={handleDepartmentChange}
          showFavorites={showFavorites}
          onToggleFavorites={handleToggleFavorites}
          searchQuery={searchQuery}
          onSearchChange={setSearchQuery}
          employeeCounts={employeeCounts}
        />
      </div>

      {/* Mobile Sidebar Overlay */}
      {showMobileSidebar && (
        <div className="lg:hidden fixed inset-0 z-50 flex">
          <div
            className="absolute inset-0 bg-black/50 backdrop-blur-sm"
            onClick={() => setShowMobileSidebar(false)}
          />
          <div className="relative w-80 max-w-[85vw] animate-slide-in-left">
            <button
              onClick={() => setShowMobileSidebar(false)}
              className="absolute top-4 right-4 w-8 h-8 rounded-lg bg-accent flex items-center justify-center z-10"
            >
              <X className="w-4 h-4" />
            </button>
            <Sidebar
              activeCompany={activeCompany}
              onCompanyChange={(company) => {
                handleCompanyChange(company);
                setShowMobileSidebar(false);
              }}
              activeDepartment={activeDepartment}
              onDepartmentChange={(dept) => {
                handleDepartmentChange(dept);
                setShowMobileSidebar(false);
              }}
              showFavorites={showFavorites}
              onToggleFavorites={() => {
                handleToggleFavorites();
                setShowMobileSidebar(false);
              }}
              searchQuery={searchQuery}
              onSearchChange={setSearchQuery}
              employeeCounts={employeeCounts}
            />
          </div>
        </div>
      )}

      {/* Main Content */}
      <main className="flex-1 flex flex-col min-w-0">
        {/* Mobile Header */}
        <MobileHeader
          searchQuery={searchQuery}
          onSearchChange={setSearchQuery}
          activeCompany={activeCompany}
          onCompanyChange={handleCompanyChange}
          onOpenSidebar={() => setShowMobileSidebar(true)}
          employeeCount={employeeCounts.total}
        />

        {/* Content Area */}
        <div className="flex-1 flex overflow-hidden">
          {/* Employee List */}
          <div
            className={cn(
              "flex-1 overflow-y-auto p-4 lg:p-6",
              showMobileDetail && "hidden lg:block"
            )}
          >
            {/* Desktop Search Banner */}
            <div className="hidden lg:block mb-6">
              <div className="flex items-center justify-between">
                <div>
                  <h1 className="text-2xl font-bold text-foreground">
                    {showFavorites
                      ? "รายการโปรด"
                      : activeDepartment
                        ? activeDepartment
                        : activeCompany === "All"
                          ? "พนักงานทั้งหมด"
                          : `พนักงาน ${activeCompany}`}
                  </h1>
                  <p className="text-sm text-muted-foreground mt-1">
                    {filteredEmployees.filter(
                      (e) => e.employment_status === "active"
                    ).length}{" "}
                    คน
                    {searchQuery && ` • ค้นหา "${searchQuery}"`}
                  </p>
                </div>
              </div>
            </div>

            <EmployeeList
              employees={filteredEmployees}
              favorites={favorites}
              onToggleFavorite={toggleFavorite}
              selectedEmployee={selectedEmployee}
              onSelectEmployee={handleSelectEmployee}
              activeCompany={activeCompany}
              activeDepartment={activeDepartment}
              showFavorites={showFavorites}
            />
          </div>

          {/* Employee Detail Panel - Desktop */}
          <div className="hidden lg:block w-96 border-l border-border bg-card shrink-0">
            <EmployeeDetail
              employee={selectedEmployeeData}
              isFavorite={selectedEmployee ? favorites.has(selectedEmployee) : false}
              onToggleFavorite={() =>
                selectedEmployee && toggleFavorite(selectedEmployee)
              }
            />
          </div>

          {/* Employee Detail - Mobile */}
          {showMobileDetail && (
            <div className="lg:hidden fixed inset-0 z-40 bg-background animate-fade-in">
              <EmployeeDetail
                employee={selectedEmployeeData}
                isFavorite={selectedEmployee ? favorites.has(selectedEmployee) : false}
                onToggleFavorite={() =>
                  selectedEmployee && toggleFavorite(selectedEmployee)
                }
                onClose={() => {
                  setShowMobileDetail(false);
                  setSelectedEmployee(null);
                }}
              />
            </div>
          )}
        </div>
      </main>
    </div>
  );
}
