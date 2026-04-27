import streamlit as st
import pandas as pd

st.set_page_config(page_title="Dashboard Misgrading", layout="wide")
st.title("📊 Dashboard Misgrading - Region 2")

file_path = "Analisis need coaching rn 2egio.csv"

# ==============================
# LOAD DATA
# ==============================
try:
    df_main = pd.read_excel(file_path, sheet_name=0, header=1)
    df_ref = pd.read_excel(file_path, sheet_name="Db cabang regin 2")
except Exception as e:
    st.error(f"Gagal membaca file: {e}")
    st.stop()

# ==============================
# RENAME KOLOM UTAMA
# ==============================
df_main.columns = [
    "Cabang","Total_Transaksi","QC_Agg","IA_Agg","Total_Agg","Rate_Agg",
    "QC_Cons","IA_Cons","Total_Cons","Rate_Cons",
    "Status_Agg","Status_Cons","Avg_Misgrade","Offload_Loss"
]

# ==============================
# RENAME KOLOM REFERENSI
# ==============================
df_ref.columns = df_ref.columns.str.strip().str.lower()

df_ref = df_ref.rename(columns={
    "cabang": "Cabang",
    "nama klaster": "Cluster",
    "am": "AM",
    "region": "Region"
})

# ==============================
# NORMALISASI CABANG
# ==============================
df_main["Cabang"] = df_main["Cabang"].astype(str).str.strip().str.upper()
df_ref["Cabang"] = df_ref["Cabang"].astype(str).str.strip().str.upper()

# ==============================
# FILTER REGION 2
# ==============================
df_ref = df_ref[df_ref["Region"] == 2]

# ==============================
# MERGE DATA
# ==============================
df = pd.merge(
    df_main,
    df_ref[["Cabang", "Cluster", "AM"]],
    on="Cabang",
    how="inner"
)

# ==============================
# CLEAN RATE
# ==============================
def clean_rate(col):
    return (
        col.astype(str)
        .str.replace("%", "", regex=False)
        .str.replace(",", ".", regex=False)
        .str.strip()
    )

df["Rate_Agg"] = pd.to_numeric(clean_rate(df["Rate_Agg"]), errors="coerce")
df["Rate_Cons"] = pd.to_numeric(clean_rate(df["Rate_Cons"]), errors="coerce")

# convert persen jika perlu
if df["Rate_Agg"].max() > 1:
    df["Rate_Agg"] /= 100
if df["Rate_Cons"].max() > 1:
    df["Rate_Cons"] /= 100

# ==============================
# MISGRADING
# ==============================
df["Misgrading"] = (df["Rate_Agg"] - df["Rate_Cons"]).abs()

# ==============================
# KATEGORI
# ==============================
def kategori(x):
    if pd.isna(x):
        return "DATA ERROR"
    elif x >= 0.05:
        return "URGENT"
    elif x >= 0.02:
        return "NEED MONITOR"
    else:
        return "AMAN"

df["Kategori"] = df["Misgrading"].apply(kategori)

# ==============================
# KPI
# ==============================
col1, col2, col3 = st.columns(3)
col1.metric("Total Cabang R2", len(df))
col2.metric("URGENT", (df["Kategori"] == "URGENT").sum())
col3.metric("MONITOR", (df["Kategori"] == "NEED MONITOR").sum())

# ==============================
# TOP CABANG
# ==============================
st.subheader("🔥 Top 10 Cabang Urgent")

top = df[df["Kategori"] == "URGENT"] \
    .sort_values(by="Misgrading", ascending=False) \
    .head(10)

st.dataframe(top[["Cabang", "Cluster", "AM", "Misgrading"]])

# ==============================
# DISTRIBUSI PERSENTASE
# ==============================
st.subheader("📊 Distribusi (%)")

dist_pct = df["Kategori"].value_counts(normalize=True) * 100
dist_pct = dist_pct.round(2)

st.dataframe(dist_pct)
st.bar_chart(dist_pct)

# ==============================
# KONTRIBUSI CLUSTER
# ==============================
st.subheader("🏢 Kontribusi Cluster (%)")

cluster_urgent = df.groupby("Cluster")["Kategori"] \
    .apply(lambda x: (x == "URGENT").sum())

cluster_pct = (cluster_urgent / cluster_urgent.sum()) * 100
cluster_pct = cluster_pct.sort_values(ascending=False).round(2)

st.dataframe(cluster_pct)

# ==============================
# KONTRIBUSI AM
# ==============================
st.subheader("👤 Kontribusi AM (%)")

am_urgent = df.groupby("AM")["Kategori"] \
    .apply(lambda x: (x == "URGENT").sum())

am_pct = (am_urgent / am_urgent.sum()) * 100
am_pct = am_pct.sort_values(ascending=False).round(2)

st.dataframe(am_pct)

# ==============================
# ANALISIS CLUSTER
# ==============================
st.subheader("🏢 Cluster Paling Bermasalah")

cluster_rank = df.groupby("Cluster")["Misgrading"].mean().sort_values(ascending=False)
st.dataframe(cluster_rank)

# ==============================
# ANALISIS AM
# ==============================
st.subheader("👤 AM Perlu Coaching")

am_rank = df.groupby("AM")["Misgrading"].mean().sort_values(ascending=False)
st.dataframe(am_rank)

# ==============================
# INSIGHT OTOMATIS
# ==============================
st.subheader("🧠 Insight")

if len(top) > 0:
    row = top.iloc[0]
    st.warning(
        f"Cabang {row['Cabang']} (Cluster {row['Cluster']}, AM {row['AM']}) "
        f"memiliki misgrading tertinggi → perlu coaching segera."
    )

if len(cluster_pct) > 0:
    st.info(
        f"Cluster {cluster_pct.index[0]} menyumbang {cluster_pct.iloc[0]}% dari total URGENT."
    )

if len(am_pct) > 0:
    st.info(
        f"AM {am_pct.index[0]} menyumbang {am_pct.iloc[0]}% dari total URGENT."
    )

# ==============================
# DATA
# ==============================
st.subheader("📋 Data Region 2")

st.dataframe(df)

# ==============================
# DOWNLOAD
# ==============================
st.download_button(
    "⬇️ Download Data",
    df.to_csv(index=False).encode("utf-8"),
    "hasil_region2.csv",
    "text/csv"
)
