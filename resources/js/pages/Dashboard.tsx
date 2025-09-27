import React, { useContext } from "react";
import { AuthContext } from "../context/AuthContext";
import { Button } from "../components/ui/button";

const Dashboard = () => {
  const { user, logout } = useContext(AuthContext);

  return (
    <div className="min-h-screen flex flex-col items-center justify-center bg-gray-50">
      <h1 className="text-3xl font-bold text-gray-800">Welcome, {user?.username}</h1>
      <p className="text-gray-600 mt-2">This is your dashboard 🚀</p>
      <Button
        onClick={logout}
        className="mt-6 bg-red-500 hover:bg-red-600 text-white"
      >
        Logout
      </Button>
    </div>
  );
};

export default Dashboard;
