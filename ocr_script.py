import sys
import pytesseract
import re
from PIL import Image
import mysql.connector
from datetime import datetime
pytesseract.pytesseract.tesseract_cmd = '/usr/bin/tesseract'
def connect_to_db():
    """Create a database connection to MySQL"""
    try:
        connection = mysql.connector.connect(
            host='localhost',
            user='root',
            password='bfbD4cPG6eL:',  # Use the correct password
            database='marksheet_ocr'
        )
        return connection
    except mysql.connector.Error as e:
        print(f"DB Connection Error: {str(e)}")
        sys.exit(1)

def check_duplicate(data):
    """Check if the data already exists in the database"""
    try:
        connection = connect_to_db()
        cursor = connection.cursor()
        query = """
            SELECT COUNT(*) FROM ocr_results
            WHERE name = %s
            AND enrollment = %s
            AND semester = %s
            AND sgpa = %s
            AND examination = %s
            AND result = %s
        """
        try:
            sgpa = float(data.get("SGPA", "0.0"))
        except ValueError:
            sgpa = 0.0
        values = (
            data.get("Name", "Not Found"),
            data.get("Enrollment", "Not Found"),
            data.get("Semester", "Not Found"),
            sgpa,
            data.get("Examination", "Not Found"),
            data.get("Result", "Not Found")
        )

        cursor.execute(query, values)
        count = cursor.fetchone()[0]

        cursor.close()
        connection.close()

        return count > 0  # Return True if a duplicate exists, False otherwise
    except mysql.connector.Error as e:
        print(f"DB Check Error: {str(e)}")
        return False

def save_to_db(data, filename):
    """Save extracted data to the database if no duplicate exists"""
    # Check for duplicates first
    if check_duplicate(data):
        return "Duplicate record found, skipping save."
    
    try:
        connection = connect_to_db()
        cursor = connection.cursor()

        # Prepare SQL query to insert data
        sql = """
            INSERT INTO ocr_results (filename, name, enrollment, semester, sgpa, examination, result, created_at)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
        """
        try:
            sgpa = float(data.get("SGPA", "0.0"))
        except ValueError:
            sgpa = 0.0

        values = (
            filename,
            data.get("Name", "Not Found"),
            data.get("Enrollment", "Not Found"),
            data.get("Semester", "Not Found"),
            sgpa,
            data.get("Examination", "Not Found"),
            data.get("Result", "Not Found"),
            datetime.now()
        )

        cursor.execute(sql, values)
        connection.commit()
        cursor.close()
        connection.close()
        return "Data saved to database."
    except mysql.connector.Error as e:
        return f"DB Save Error: {str(e)}"

def extract_info_from_image(img_path):
    try:
        # Open image and perform OCR
        img = Image.open(img_path)
        ocr_result = pytesseract.image_to_string(img)
        # Clean the OCR text
        clean_text = re.sub(r"[^\w\s:/.-]", "", ocr_result)
        clean_text = re.sub(r"\s+", " ", clean_text).strip()
        extracted_info = {}
        name_match = re.search(r"([A-Z]+ [A-Z]+),?\s*S/D/W/O\s*[A-Z]+", clean_text)
        if name_match:
            extracted_info["Name"] = name_match.group(1).strip()
        else:
            alt_name_match = re.search(r"([A-Z]+ [A-Z]+)\s+Roll No", clean_text)
            if alt_name_match:
                extracted_info["Name"] = alt_name_match.group(1).strip()
            else:
                extracted_info["Name"] = "Not Found"
        patterns = {
            "Enrollment": r"(?:eDocId|Enrollment No)[:\s]*([A-Z0-9]+)",
            "Semester": r"(?:SEMESTER|SEM)[.\s]*[:-]?\s*(\w+)",
            "SGPA": r"(?:SGPA|GPA)[.\s]*[:-]?\s*([\d.]+)",
            "Examination": r"(?:EXAMINATION|EXAM)[.\s]*[:-]?\s*([A-Z]+\s*-\s*\d{4})"
        }

        for key, pattern in patterns.items():
            match = re.search(pattern, clean_text, re.IGNORECASE)
            extracted_info[key] = match.group(1).strip() if match else "Not Found"

        if "Enrollment" in extracted_info and extracted_info["Enrollment"] != "Not Found":
            if len(extracted_info["Enrollment"]) > 11:
                extracted_info["Enrollment"] = extracted_info["Enrollment"][:11]
        result_patterns = [
            r"RESULT\s*[:_\-]\s*([A-Z\s]+?)(?=\s*SGPA|PERCENTAGE|DIVISION|MARKS)",
            r"RESULT\s*[:_\-]\s*([A-Z\s]+)",
            r"(?:PASS|FAIL)(?:\s+WITH\s+[A-Z]+)?",
            r"DECLARED\s+AS\s+([A-Z]+)"
        ]
        result = "Not Found"
        for pattern in result_patterns:
            match = re.search(pattern, clean_text, re.IGNORECASE)
            if match:
                if len(match.groups()) > 0:
                    result = match.group(1).strip()
                else:
                    result = match.group(0).strip()
                break
        if result == "Not Found":
            if re.search(r"\bPASS\b", clean_text, re.IGNORECASE):
                result = "PASS"
            elif re.search(r"\bFAIL\b", clean_text, re.IGNORECASE):
                result = "FAIL"
        extracted_info["Result"] = result

        return extracted_info
    except Exception as e:
        return {"Error": f"Failed to process {img_path}: {str(e)}"}

def main():
    # Check for correct number of arguments
    if len(sys.argv) != 2:
        print("Usage: python3 ocr_script.py <image_path>")
        sys.exit(1)

    image_path = sys.argv[1]
    filename = image_path.split('/')[-1]  # Extract the filename
    print(f"Processing image: {image_path}")
    extracted_data = extract_info_from_image(image_path)
    if "Error" in extracted_data:
        print(extracted_data["Error"])
    else:
        # Save to database (with duplicate check)
        save_result = save_to_db(extracted_data, filename)
        print(save_result)
        for key, value in extracted_data.items():
            print(f"{key:<12}: {value}")
if __name__ == "__main__":
    main()
