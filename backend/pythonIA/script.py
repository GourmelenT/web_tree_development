import folium
import joblib
import pandas as pd
from folium.plugins import GroupedLayerControl
from pyproj import Transformer
from sklearn.cluster import KMeans
from sklearn.preprocessing import StandardScaler


DATA_FILE = "export_IA.csv"
FEATURES = ["haut_tot", "tronc_diam"]
CATEGORY_COLORS = {
    "Petit": "green",
    "Moyen": "orange",
    "Grand": "red",
}


def load_and_prepare_data(csv_path: str) -> pd.DataFrame:
    data = pd.read_csv(csv_path)

    data_clean = data.copy()
    for col in FEATURES:
        data_clean[col] = pd.to_numeric(data_clean[col], errors="coerce")

    data_clean = data_clean.dropna(subset=FEATURES)
    data_clean = data_clean[
        (data_clean["haut_tot"] > 1)
        & (data_clean["haut_tot"] < 35)
        & (data_clean["tronc_diam"] > 5)
        & (data_clean["tronc_diam"] < 150)
    ]

    return data_clean


def train_model_k3(data_clean: pd.DataFrame):
    scaler = StandardScaler()
    x_scaled = scaler.fit_transform(data_clean[FEATURES])

    kmeans = KMeans(n_clusters=3, random_state=42, n_init=20)
    clusters = kmeans.fit_predict(x_scaled)

    data_clean = data_clean.copy()
    data_clean["cluster_3"] = clusters

    centroids = scaler.inverse_transform(kmeans.cluster_centers_)
    centroids_df = pd.DataFrame(centroids, columns=FEATURES)
    centroids_df["cluster"] = range(3)
    centroids_df = centroids_df.sort_values(by="haut_tot").reset_index(drop=True)

    # Ordered labels on sorted centroids by height: small -> medium -> large
    centroids_df["cat_pmg"] = ["Petit", "Moyen", "Grand"]

    mapping_pmg = dict(zip(centroids_df["cluster"], centroids_df["cat_pmg"]))
    mapping_pg = {cluster: ("Petit" if cat == "Petit" else "Grand") for cluster, cat in mapping_pmg.items()}
    mapping_mg = {cluster: ("Grand" if cat == "Grand" else "Moyen") for cluster, cat in mapping_pmg.items()}

    data_clean["cat_pg"] = data_clean["cluster_3"].map(mapping_pg)
    data_clean["cat_mg"] = data_clean["cluster_3"].map(mapping_mg)
    data_clean["cat_pmg"] = data_clean["cluster_3"].map(mapping_pmg)

    joblib.dump(scaler, "scaler.pkl")
    joblib.dump(kmeans, "kmeans_3.pkl")
    joblib.dump(mapping_pg, "mapping_pg.pkl")
    joblib.dump(mapping_mg, "mapping_mg.pkl")
    joblib.dump(mapping_pmg, "mapping_pmg.pkl")

    print("Modeles sauvegardes: scaler.pkl, kmeans_3.pkl, mapping_*.pkl")

    return data_clean


def add_coordinates(data_clean: pd.DataFrame) -> pd.DataFrame:
    data_map = data_clean.copy()
    data_map["X"] = pd.to_numeric(data_map["X"], errors="coerce")
    data_map["Y"] = pd.to_numeric(data_map["Y"], errors="coerce")
    data_map = data_map.dropna(subset=["X", "Y"])

    transformer = Transformer.from_crs("EPSG:3949", "EPSG:4326", always_xy=True)
    lon, lat = transformer.transform(data_map["X"].values, data_map["Y"].values)
    data_map["lon"] = lon
    data_map["lat"] = lat

    return data_map


def add_layer(data_map: pd.DataFrame, category_col: str, layer_name: str, base_map: folium.Map) -> folium.FeatureGroup:
    group = folium.FeatureGroup(name=layer_name, show=(category_col == "cat_pmg"))

    for _, row in data_map.iterrows():
        category = row[category_col]
        folium.CircleMarker(
            location=[row["lat"], row["lon"]],
            radius=4,
            color=CATEGORY_COLORS.get(category, "gray"),
            fill=True,
            fill_opacity=0.75,
            popup=(
                f"Hauteur: {row['haut_tot']} m<br>"
                f"Diametre: {row['tronc_diam']} cm<br>"
                f"Categorie: {category}"
            ),
        ).add_to(group)

    group.add_to(base_map)
    return group


def add_legend(base_map: folium.Map) -> None:
        legend_html = """
        <div style="
                position: fixed;
                bottom: 30px;
                right: 20px;
                z-index: 9999;
                background: white;
                border: 2px solid #444;
                border-radius: 8px;
                padding: 10px 12px;
                box-shadow: 0 0 6px rgba(0,0,0,0.3);
                font-size: 13px;
                line-height: 1.5;
        ">
            <div style="font-weight: bold; margin-bottom: 6px;">Legende categories</div>
            <div><span style="color: green;">●</span> Petit</div>
            <div><span style="color: orange;">●</span> Moyen</div>
            <div><span style="color: red;">●</span> Grand</div>
        </div>
        """
        base_map.get_root().html.add_child(folium.Element(legend_html))


def create_interactive_map(data_map: pd.DataFrame, output_html: str = "carte_arbres.html") -> None:
    city_center = [49.8489, 3.2873]
    m = folium.Map(location=city_center, zoom_start=13, tiles="CartoDB Voyager")

    layer_pg = add_layer(data_map, "cat_pg", "Cluster Petit / Grand", m)
    layer_mg = add_layer(data_map, "cat_mg", "Cluster Moyen / Grand", m)
    layer_pmg = add_layer(data_map, "cat_pmg", "Cluster Petit / Moyen / Grand", m)

    GroupedLayerControl(
        groups={"Choix du cluster": [layer_pg, layer_mg, layer_pmg]},
        exclusive_groups=True,
        collapsed=False,
    ).add_to(m)
    add_legend(m)
    m.save(output_html)
    print(f"Carte interactive generee: {output_html}")


def main() -> None:
    data_clean = load_and_prepare_data(DATA_FILE)
    data_clustered = train_model_k3(data_clean)
    data_map = add_coordinates(data_clustered)
    create_interactive_map(data_map, "carte_arbres.html")


if __name__ == "__main__":
    main()