// Plan and movement formatting shared by the Planning page and its dialogs.

// Mirrors Movement::formatCode() on the server.
export const formatMovementCode = (n) => `TRP-${String(n).padStart(5, "0")}`;

// Mirrors MovementTemplate::legMinutes(): a movement window's length in minutes.
export function legMinutes(template, leg) {
  const total = Number(template?.estimated_duration_minutes) || 0;
  const count = Math.max(1, template?.legs?.length || 0);
  if (count === 1 && total) return total;
  return Number(leg?.estimated_duration_minutes) || (total ? Math.round(total / count) : 30);
}

export function formatDateTime(dateString) {
  if (!dateString) return "";

  // Handle "YYYY-MM-DD HH:mm:ss" format without timezone conversion
  if (
    typeof dateString === "string" &&
    dateString.match(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/)
  ) {
    const [datePart, timePart] = dateString.split(" ");
    const [year, month, day] = datePart.split("-");
    const [hour, minute] = timePart.split(":");
    const monthNames = [
      "Jan",
      "Feb",
      "Mar",
      "Apr",
      "May",
      "Jun",
      "Jul",
      "Aug",
      "Sep",
      "Oct",
      "Nov",
      "Dec",
    ];
    return `${day} ${monthNames[parseInt(month) - 1]} ${hour}:${minute}`;
  }

  // Fallback: just return date for other formats
  const date = new Date(dateString);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

export function formatDate(dateString) {
  if (!dateString) return "";
  
  // Handle different date formats
  let date;
  if (typeof dateString === 'string') {
    // If already contains time, use as-is
    if (dateString.includes('T') || dateString.includes(' ')) {
      date = new Date(dateString);
    } else {
      // YYYY-MM-DD format - add time to avoid timezone issues
      date = new Date(dateString + "T00:00:00");
    }
  } else {
    date = new Date(dateString);
  }
  
  // Check if date is valid
  if (isNaN(date.getTime())) {
    return dateString; // Return original if invalid
  }
  
  const monthNames = [
    "Jan",
    "Feb",
    "Mar",
    "Apr",
    "May",
    "Jun",
    "Jul",
    "Aug",
    "Sep",
    "Oct",
    "Nov",
    "Dec",
  ];
  const day = date.getDate();
  const month = monthNames[date.getMonth()];
  const year = date.getFullYear();
  return `${day} ${month} ${year}`;
}
