export interface PropertyReportItem {
  property_id: string;
  property_name: string;
  invoiced: number;
  collected: number;
  outstanding: number;
}

export interface FinancialReport {
  period: string;
  total_invoiced: number;
  total_collected: number;
  total_outstanding: number;
  collection_rate: number;
  by_property: PropertyReportItem[];
}
